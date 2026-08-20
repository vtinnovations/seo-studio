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

use Psr\Log\LoggerInterface;

/**
 * The only way provisioning code writes to the application log.
 *
 * Ordinary logs must never carry packet material, so the context is filtered
 * against a strict ALLOWLIST rather than a blocklist: a future field that
 * someone forgets to think about is dropped by default instead of leaked.
 *
 * Permitted: request id, operation, generic result category, HTTP status,
 * elapsed milliseconds, applied licence version, normalized domain and the
 * public signing key id (useful for rotation diagnostics).
 *
 * Never permitted, and impossible to pass through this class: whole request or
 * response bodies, nonces, authentication headers, licence keys or any
 * fingerprint/length/hash of one, raw or Base64 payloads, the MD5 tripwire,
 * signatures, canonical signing input, body/response SHA-256 values, raw remote
 * errors, stack traces and internal paths.
 */
final class OperationLog
{
    /**
     * @var list<string>
     */
    private const ALLOWED = [
        'request_id',
        'operation',
        'result',
        'http_status',
        'elapsed_ms',
        'license_version',
        'domain',
        'key_id',
    ];

    private const MAX_VALUE_LENGTH = 253;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->safe($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->safe($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->safe($context));
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, scalar>
     */
    public function safe(array $context): array
    {
        $out = [];

        foreach (self::ALLOWED as $key) {
            if (!\array_key_exists($key, $context)) {
                continue;
            }

            $value = $context[$key];

            if (\is_bool($value) || \is_int($value)) {
                $out[$key] = $value;

                continue;
            }

            if (\is_string($value) && $value !== '') {
                $out[$key] = substr($value, 0, self::MAX_VALUE_LENGTH);
            }
        }

        return $out;
    }
}
