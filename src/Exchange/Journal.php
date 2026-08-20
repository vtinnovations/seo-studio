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

use Doctrine\DBAL\Connection;

/**
 * Replay and idempotency journal for vendor-initiated updates.
 *
 * The claim is a plain INSERT against a UNIQUE request id, which makes it
 * atomic even when several web nodes receive the same retry simultaneously —
 * exactly one wins, the others learn that the id already exists.
 *
 * Semantics required by the exchange contract:
 *   - a genuinely new, authenticated request is claimed and processed once;
 *   - an exact retry (same id, same authenticated body) reports the earlier
 *     result without applying anything again;
 *   - the same id with different content is a security event, never an update;
 *   - a nonce seen under a different request id is a replay attempt.
 *
 * Only digests are stored — never the nonce itself, never the payload. These
 * rows exist for security decisions; they are not log output and are never
 * written to the application log.
 */
final class Journal
{
    public const CLAIMED = 'claimed';

    public const DUPLICATE_MATCH = 'duplicate_match';

    public const DUPLICATE_CONFLICT = 'duplicate_conflict';

    public const NONCE_REPLAY = 'nonce_replay';

    public const UNAVAILABLE = 'journal_unavailable';

    /** Kept well beyond any legitimate retry window, then pruned. */
    private const RETENTION_SECONDS = 2592000;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function claim(string $requestId, string $nonceDigest, string $bodyDigest, int $now): string
    {
        try {
            $seenNonce = $this->connection->fetchOne(
                'SELECT requestId FROM tl_seo_studio_exchange WHERE nonceDigest = ? LIMIT 1',
                [$nonceDigest],
            );

            if (\is_string($seenNonce) && !hash_equals($requestId, $seenNonce)) {
                return self::NONCE_REPLAY;
            }

            $existing = $this->connection->fetchAssociative(
                'SELECT bodyDigest, appliedVersion FROM tl_seo_studio_exchange WHERE requestId = ?',
                [$requestId],
            );

            if (\is_array($existing)) {
                $stored = (string) ($existing['bodyDigest'] ?? '');

                return hash_equals($stored, $bodyDigest) ? self::DUPLICATE_MATCH : self::DUPLICATE_CONFLICT;
            }

            $this->connection->insert('tl_seo_studio_exchange', [
                'tstamp' => $now,
                'requestId' => $requestId,
                'nonceDigest' => $nonceDigest,
                'bodyDigest' => $bodyDigest,
                'processedAt' => 0,
                'result' => 'pending',
                'appliedVersion' => 0,
            ]);

            return self::CLAIMED;
        } catch (\Throwable) {
            // Lost the insert race (unique index) or the table is unavailable.
            // Either way this request must not be applied twice.
            try {
                $existing = $this->connection->fetchAssociative(
                    'SELECT bodyDigest FROM tl_seo_studio_exchange WHERE requestId = ?',
                    [$requestId],
                );
            } catch (\Throwable) {
                return self::UNAVAILABLE;
            }

            if (!\is_array($existing)) {
                return self::UNAVAILABLE;
            }

            return hash_equals((string) ($existing['bodyDigest'] ?? ''), $bodyDigest)
                ? self::DUPLICATE_MATCH
                : self::DUPLICATE_CONFLICT;
        }
    }

    public function complete(string $requestId, string $result, int $appliedVersion, int $now): void
    {
        try {
            $this->connection->update(
                'tl_seo_studio_exchange',
                ['result' => substr($result, 0, 32), 'appliedVersion' => $appliedVersion, 'processedAt' => $now, 'tstamp' => $now],
                ['requestId' => $requestId],
            );
        } catch (\Throwable) {
            // A missing journal update must not turn a completed, verified
            // activation into an error for the vendor.
        }
    }

    public function appliedVersion(string $requestId): int
    {
        try {
            $value = $this->connection->fetchOne(
                'SELECT appliedVersion FROM tl_seo_studio_exchange WHERE requestId = ?',
                [$requestId],
            );
        } catch (\Throwable) {
            return 0;
        }

        return \is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Bounded retention. Called opportunistically after a successful exchange
     * so the table cannot grow without limit on a busy installation.
     */
    public function prune(int $now): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM tl_seo_studio_exchange WHERE tstamp < ?',
                [$now - self::RETENTION_SECONDS],
            );
        } catch (\Throwable) {
            // Housekeeping only.
        }
    }
}
