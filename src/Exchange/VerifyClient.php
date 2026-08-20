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

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Security\CanonicalForm;

/**
 * Outbound activation and administrator refresh.
 *
 * Transport hardening, all non-negotiable:
 *   - one fixed HTTPS destination, TLS peer and host verification on;
 *   - redirects disabled, so a 302 can never move the conversation elsewhere;
 *   - bounded connect/total time and a hard response-size cap;
 *   - media type checked before anything is parsed;
 *   - a fresh request id and single-use nonce per attempt, and the answer must
 *     echo the request id back;
 *   - only the documented fields are sent — no diagnostics, no host details.
 *
 * Nothing here writes packet content to the log: transport results are recorded
 * through OperationLog, which physically cannot carry payloads, nonces, keys,
 * digests or signatures.
 */
final class VerifyClient
{
    private const MAX_RESPONSE_BYTES = 65536;

    private const MAX_SKEW_SECONDS = 900;

    public const ACTION_ACTIVATE = 'activate';

    public const ACTION_REFRESH = 'refresh';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OperationLog $log,
    ) {
    }

    /**
     * @param positive-int|null $currentVersion required for a refresh
     */
    public function exchange(string $action, string $licenceKey, string $domain, ?int $currentVersion, int $now): VerifyOutcome
    {
        $requestId = bin2hex(random_bytes(16));

        $payload = [
            'action' => $action,
            'project' => PackagePolicy::PROJECT,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'product_id' => PackagePolicy::PRODUCT_ID,
            'license_key' => $licenceKey,
            'domain' => $domain,
            'request_id' => $requestId,
            'timestamp' => $now,
            'nonce' => bin2hex(random_bytes(16)),
        ];

        if ($action === self::ACTION_REFRESH) {
            $payload['current_license_version'] = $currentVersion ?? 0;
        }

        $started = microtime(true);

        try {
            $response = $this->httpClient->request('POST', Endpoint::verify(), [
                'json' => $payload,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'max_redirects' => 0,
                'timeout' => 10,
                'max_duration' => 20,
                'verify_peer' => true,
                'verify_host' => true,
                'buffer' => true,
            ]);

            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $body = $response->getContent(false);
        } catch (HttpExceptionInterface|\Throwable) {
            // Network error, TLS failure, timeout: the previously valid state
            // must survive untouched.
            $this->log->warning('SEO Studio provisioning exchange unavailable', [
                'operation' => $action,
                'request_id' => $requestId,
                'result' => 'transport_failure',
                'elapsed_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);

            return VerifyOutcome::failed('transport_failure', 0, $requestId);
        }

        $elapsed = (int) ((microtime(true) - $started) * 1000);

        $outcome = $this->interpret($status, $headers, $body, $requestId, $now);

        $this->log->info('SEO Studio provisioning exchange completed', [
            'operation' => $action,
            'request_id' => $requestId,
            'result' => $outcome->category,
            'http_status' => $status,
            'elapsed_ms' => $elapsed,
            'domain' => $domain,
        ]);

        return $outcome;
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function interpret(int $status, array $headers, string $body, string $requestId, int $now): VerifyOutcome
    {
        if ($status < 200 || $status > 299) {
            return VerifyOutcome::failed($status >= 500 ? 'transport_failure' : 'rejected_by_vendor', $status, $requestId);
        }

        $contentType = strtolower($headers['content-type'][0] ?? '');
        if (!str_contains($contentType, 'application/json')) {
            return VerifyOutcome::failed('response_malformed', $status, $requestId);
        }

        if ($body === '' || \strlen($body) > self::MAX_RESPONSE_BYTES) {
            return VerifyOutcome::failed('response_malformed', $status, $requestId);
        }

        try {
            $decoded = CanonicalForm::decode($body);
        } catch (\JsonException) {
            return VerifyOutcome::failed('response_malformed', $status, $requestId);
        }

        if (!$decoded instanceof \stdClass) {
            return VerifyOutcome::failed('response_malformed', $status, $requestId);
        }

        $echoed = $decoded->request_id ?? null;
        if (!\is_string($echoed) || !hash_equals($requestId, $echoed)) {
            return VerifyOutcome::failed('correlation_mismatch', $status, $requestId);
        }

        $serverTime = $decoded->server_time ?? null;
        if (!\is_int($serverTime) || abs($serverTime - $now) > self::MAX_SKEW_SECONDS) {
            return VerifyOutcome::failed('server_time_skew', $status, $requestId);
        }

        $vendorStatus = $decoded->status ?? null;
        if ($vendorStatus !== 'valid') {
            // An authenticated denial. The caller keeps whatever was already
            // valid; it never fabricates a replacement tier locally.
            return VerifyOutcome::failed('denied_by_vendor', $status, $requestId);
        }

        $payloadB64 = $decoded->license_payload_b64 ?? null;
        $envelope = $decoded->integrity ?? null;

        if (!\is_string($payloadB64) || $payloadB64 === '' || !$envelope instanceof \stdClass) {
            return VerifyOutcome::failed('response_malformed', $status, $requestId);
        }

        return VerifyOutcome::received($payloadB64, $envelope, $status, $requestId);
    }
}
