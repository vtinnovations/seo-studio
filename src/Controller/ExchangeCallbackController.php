<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SeoStudio\Exchange\InboundRequestCheck;
use VTinnovations\SeoStudio\Exchange\Journal;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;

/**
 * Public endpoint for vendor-initiated provisioning updates.
 *
 * Deliberately thin: it enforces request-shape limits (method, media type,
 * size), then hands off. Authentication, replay handling, package verification
 * and the atomic swap all live elsewhere, so this file contains no key
 * material, no signature logic, no digest logic and no persistence.
 *
 * It is reachable without a backend login because the caller is another server
 * and has no browser session — which is exactly why every request must carry a
 * valid vendor signature. Answers are generic on purpose: a caller learns
 * whether it succeeded, never which check failed.
 *
 * This handler never writes application source, never takes a path from the
 * request and never evaluates request data as code.
 */
final class ExchangeCallbackController
{
    public function __construct(
        private readonly InboundRequestCheck $check,
        private readonly Journal $journal,
        private readonly ProvisioningWorkflow $workflow,
        private readonly OperationLog $log,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            // A wrong method is a wrong method, not a missing endpoint.
            return new JsonResponse(['status' => 'method_not_allowed'], Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
        }

        $mediaType = strtolower((string) $request->headers->get('Content-Type', ''));
        if (!str_contains($mediaType, 'application/json')) {
            return new JsonResponse(['status' => 'unsupported_media_type'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $declaredLength = (int) $request->headers->get('Content-Length', '0');
        if ($declaredLength > InboundRequestCheck::MAX_BODY_BYTES) {
            return new JsonResponse(['status' => 'payload_too_large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        if (\strlen($request->getContent()) > InboundRequestCheck::MAX_BODY_BYTES) {
            return new JsonResponse(['status' => 'payload_too_large'], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $now = time();
        $authenticated = $this->check->authenticate($request, $now);

        if (!$authenticated->authenticated) {
            $this->log->warning('SEO Studio provisioning callback refused', [
                'operation' => 'push',
                'result' => $authenticated->category,
                'http_status' => Response::HTTP_UNAUTHORIZED,
            ]);

            return new JsonResponse(['status' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $claim = $this->journal->claim(
            $authenticated->requestId,
            $authenticated->nonceDigest,
            $authenticated->bodyDigest,
            $now,
        );

        if ($claim === Journal::NONCE_REPLAY) {
            return new JsonResponse(['status' => 'unauthorized'], Response::HTTP_FORBIDDEN);
        }

        if ($claim === Journal::DUPLICATE_CONFLICT) {
            // Same request id, different authenticated content: a security
            // event, never an update.
            $this->log->warning('SEO Studio provisioning callback conflict', [
                'operation' => 'push',
                'request_id' => $authenticated->requestId,
                'result' => $claim,
            ]);

            return new JsonResponse(['status' => 'conflict'], Response::HTTP_CONFLICT);
        }

        if ($claim === Journal::DUPLICATE_MATCH) {
            // Exact retry of something already applied.
            return new JsonResponse([
                'status' => 'already_processed',
                'request_id' => $authenticated->requestId,
                'license_version' => $this->journal->appliedVersion($authenticated->requestId),
            ]);
        }

        if ($claim === Journal::UNAVAILABLE) {
            return new JsonResponse(['status' => 'unavailable'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $outcome = $this->workflow->apply($authenticated, $now);

        if ($outcome['status'] !== ProvisioningWorkflow::OK) {
            $this->journal->complete($authenticated->requestId, 'rejected', 0, $now);

            $conflict = \in_array($outcome['status'], ['version_rollback', 'envelope_document_mismatch'], true);

            return new JsonResponse(
                ['status' => 'rejected', 'request_id' => $authenticated->requestId],
                $conflict ? Response::HTTP_CONFLICT : Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->journal->complete($authenticated->requestId, 'updated', $outcome['version'], $now);

        return new JsonResponse([
            'status' => 'updated',
            'request_id' => $authenticated->requestId,
            'license_version' => $outcome['version'],
        ]);
    }
}
