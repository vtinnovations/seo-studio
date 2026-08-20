<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Exchange;

use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Content\HostName;
use VTinnovations\SeoStudio\Core\Security\CanonicalForm;
use VTinnovations\SeoStudio\Core\Security\SignatureVerifier;
use VTinnovations\SeoStudio\Core\Security\TrustAnchor;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;

/**
 * Authenticates a vendor-initiated update request.
 *
 * The endpoint is public because it is server-to-server: there is no browser
 * session and no CSRF token to rely on. Trust therefore comes ONLY from
 * cryptography. A claimed Origin, Referer, User-Agent, reverse DNS entry or
 * source IP proves nothing here and is not consulted.
 *
 * Signed message ("vt-one/request-sig-v1") — six lines joined with "\n" and no
 * trailing newline:
 *
 *   1 uppercased HTTP method
 *   2 exact updater path as served
 *   3 request id
 *   4 UTC unix timestamp as a decimal string
 *   5 nonce
 *   6 lowercase hex SHA-256 of the exact raw body bytes
 *
 * X-VT-Key-ID selects the pinned key and is intentionally NOT one of those
 * lines; the duplicated metadata in headers and body must still agree exactly,
 * so a body cannot describe one request while the headers sign another.
 */
final class InboundRequestCheck
{
    public const MAX_BODY_BYTES = 65536;

    private const MAX_SKEW_SECONDS = 300;

    public function __construct(
        private readonly SignatureVerifier $verifier,
        private readonly TrustAnchors $anchors,
    ) {
    }

    public function authenticate(Request $request, int $now): InboundRequest
    {
        if ($this->anchors->isEmpty()) {
            return InboundRequest::refused(TrustAnchors::CATEGORY_EMPTY);
        }

        $requestId = (string) $request->headers->get('X-VT-Request-ID', '');
        $timestamp = (string) $request->headers->get('X-VT-Timestamp', '');
        $nonce = (string) $request->headers->get('X-VT-Nonce', '');
        $keyId = (string) $request->headers->get('X-VT-Key-ID', '');
        $signature = (string) $request->headers->get('X-VT-Signature', '');

        if ($requestId === '' || $timestamp === '' || $nonce === '' || $keyId === '' || $signature === '') {
            return InboundRequest::refused('authentication_incomplete');
        }

        if (preg_match('/^\d{1,12}$/', $timestamp) !== 1) {
            return InboundRequest::refused('timestamp_invalid');
        }

        $signedAt = (int) $timestamp;
        if (abs($now - $signedAt) > self::MAX_SKEW_SECONDS) {
            return InboundRequest::refused('timestamp_outside_window');
        }

        $raw = $request->getContent();
        if ($raw === '' || \strlen($raw) > self::MAX_BODY_BYTES) {
            return InboundRequest::refused('body_size');
        }

        $algorithm = TrustAnchor::ALGORITHM_ED25519;
        if ($this->anchors->find($keyId, $algorithm, TrustAnchor::PURPOSE_REQUEST, $now) === null) {
            return InboundRequest::refused(TrustAnchors::CATEGORY_UNKNOWN);
        }

        $bodyDigest = hash('sha256', $raw);
        $verified = false;

        foreach ($this->pathCandidates($request) as $path) {
            $message = implode("\n", [
                strtoupper($request->getMethod()),
                $path,
                $requestId,
                (string) $signedAt,
                $nonce,
                $bodyDigest,
            ]);

            if ($this->verifier->verifyNamedKey($keyId, $algorithm, TrustAnchor::PURPOSE_REQUEST, $message, $signature, $now)) {
                $verified = true;

                break;
            }
        }

        if (!$verified) {
            return InboundRequest::refused('request_signature_invalid');
        }

        try {
            $body = CanonicalForm::decode($raw);
        } catch (\JsonException) {
            return InboundRequest::refused('body_malformed');
        }

        if (!$body instanceof \stdClass) {
            return InboundRequest::refused('body_malformed');
        }

        // Header/body agreement: identical request id, timestamp and nonce.
        if (
            !\is_string($body->request_id ?? null) || !hash_equals($requestId, (string) $body->request_id)
            || !\is_string($body->nonce ?? null) || !hash_equals($nonce, (string) $body->nonce)
            || !\is_int($body->timestamp ?? null) || $body->timestamp !== $signedAt
        ) {
            return InboundRequest::refused('metadata_mismatch');
        }

        if (
            ($body->action ?? null) !== 'license_update'
            || ($body->project ?? null) !== PackagePolicy::PROJECT
            || ($body->project_slug ?? null) !== PackagePolicy::PROJECT_SLUG
            || ($body->product_id ?? null) !== PackagePolicy::PRODUCT_ID
        ) {
            return InboundRequest::refused('product_mismatch');
        }

        $domain = HostName::normalize(\is_string($body->domain ?? null) ? (string) $body->domain : null);
        if ($domain === null) {
            return InboundRequest::refused('domain_invalid');
        }

        return InboundRequest::trusted($body, $requestId, hash('sha256', $nonce), $bodyDigest, $domain);
    }

    /**
     * The path the vendor signed. The served path is tried first; the canonical
     * product path is a code constant and is tried as well so that a proxy which
     * rewrites the prefix cannot break authentication. Neither candidate weakens
     * the check: a forged request still has to produce a valid signature.
     *
     * @return list<string>
     */
    private function pathCandidates(Request $request): array
    {
        $served = $request->getBaseUrl() . $request->getPathInfo();
        $canonical = Endpoint::updaterPath();

        return $served === $canonical ? [$served] : [$served, $canonical];
    }
}
