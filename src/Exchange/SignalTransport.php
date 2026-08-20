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

use Symfony\Contracts\HttpClient\HttpClientInterface;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;

/**
 * The two server-to-server signals, both to the same fixed vendor endpoint.
 *
 * They are DISTINCT event shapes and are never merged:
 *
 *   per invocation  -> {"project": "...", "domain": "..."}          (never a key)
 *   first module    -> {"domain": "...", "key": "<full licence key>"}
 *   entry in a
 *   backend session
 *
 * Both are fire-and-forget: HTTPS with peer/host verification, no redirects,
 * short connect/total timeouts, protocol restricted to HTTPS, response body
 * discarded unread, no user-visible output, and no influence whatsoever on
 * licence validity or module rendering. cURL is used where the extension is
 * present, otherwise the framework HTTP client with equivalent controls.
 *
 * The module-entry event is the single place in this product where the full
 * licence key leaves the server. It is never logged here, never returned, and
 * never handed to the browser.
 */
final class SignalTransport
{
    private const CONNECT_TIMEOUT = 3;

    private const TOTAL_TIMEOUT = 5;

    /**
     * @param bool|null $useCurl null = use cURL when the extension is present.
     *                           Tests pass false so no test can ever open a real
     *                           connection to the vendor endpoint.
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OperationLog $log,
        private readonly ?bool $useCurl = null,
    ) {
    }

    /**
     * The exact per-invocation field set. Separate from sending so tests can
     * assert the allowlist itself — a signal must never grow extra fields.
     *
     * @return array{project: string, domain: string}
     */
    public static function invocationPayload(string $domain): array
    {
        return ['project' => PackagePolicy::PROJECT, 'domain' => $domain];
    }

    /**
     * The exact module-entry field set: normalized host and full key, nothing
     * else.
     *
     * @return array{domain: string, key: string}
     */
    public static function moduleEntryPayload(string $domain, string $licenceKey): array
    {
        return ['domain' => $domain, 'key' => $licenceKey];
    }

    /**
     * Per-invocation signal. Carries only the product name and the normalized
     * host — deliberately no key, no version, no user data.
     */
    public function invocation(string $domain): void
    {
        $this->send(self::invocationPayload($domain), 'invocation', $domain);
    }

    /**
     * First-module-entry signal for the current authenticated backend session.
     * The key must come from an already authenticated signed record.
     */
    public function moduleEntry(string $domain, string $licenceKey): void
    {
        if ($domain === '' || $licenceKey === '') {
            return;
        }

        $this->send(self::moduleEntryPayload($domain, $licenceKey), 'module_entry', $domain);
    }

    /**
     * @param array<string, string> $payload exact documented field set
     */
    private function send(array $payload, string $operation, string $domain): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return;
        }

        $delivered = ($this->useCurl ?? \function_exists('curl_init'))
            ? $this->sendWithCurl($body)
            : $this->sendWithFramework($body);

        // Only the operation and the host are recorded. The payload itself —
        // including the key in the module-entry shape — never reaches a log.
        $this->log->info('SEO Studio usage signal dispatched', [
            'operation' => $operation,
            'result' => $delivered ? 'sent' : 'unavailable',
            'domain' => $domain,
        ]);
    }

    private function sendWithCurl(string $body): bool
    {
        $handle = curl_init();
        if ($handle === false) {
            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_URL => Endpoint::signal(),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_NOSIGNAL => true,
        ]);

        $result = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        // The body is discarded unread on purpose: nothing the endpoint returns
        // may influence this installation.
        unset($result);

        return $status >= 200 && $status <= 299;
    }

    private function sendWithFramework(string $body): bool
    {
        try {
            $response = $this->httpClient->request('POST', Endpoint::signal(), [
                'body' => $body,
                'headers' => ['Content-Type' => 'application/json'],
                'max_redirects' => 0,
                'timeout' => self::CONNECT_TIMEOUT,
                'max_duration' => self::TOTAL_TIMEOUT,
                'verify_peer' => true,
                'verify_host' => true,
            ]);

            $status = $response->getStatusCode();

            return $status >= 200 && $status <= 299;
        } catch (\Throwable) {
            return false;
        }
    }
}
