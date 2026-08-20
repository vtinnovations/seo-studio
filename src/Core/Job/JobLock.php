<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Job;

use Doctrine\DBAL\Connection;

/**
 * DB-row lock with TTL for cron jobs (shared hosting: no flock across
 * requests, no semaphores). Lock rows live in tl_seo_studio_config as
 * "lock:<name>" => expiry timestamp.
 *
 * Acquisition is race-safe enough for cron cadence: the UPDATE claims an
 * expired/released lock atomically; the INSERT path relies on the unique
 * index on `name` (a duplicate insert loses the race and returns false).
 */
final class JobLock
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function acquire(string $name, int $ttlSeconds = 900): bool
    {
        $key = 'lock:' . $name;
        $now = time();
        $expiry = (string) ($now + $ttlSeconds);

        // Claim an existing, expired (or released = "0") lock atomically.
        $claimed = $this->connection->executeStatement(
            'UPDATE tl_seo_studio_config SET tstamp = ?, value = ? WHERE name = ? AND CAST(value AS SIGNED) < ?',
            [$now, $expiry, $key, $now],
        );

        if ($claimed > 0) {
            return true;
        }

        // Row exists and is still held?
        $existing = $this->connection->fetchOne(
            'SELECT value FROM tl_seo_studio_config WHERE name = ?',
            [$key],
        );

        if ($existing !== false) {
            return false;
        }

        try {
            $this->connection->insert('tl_seo_studio_config', [
                'tstamp' => $now,
                'name' => $key,
                'value' => $expiry,
            ]);
        } catch (\Throwable) {
            return false; // Lost the insert race.
        }

        return true;
    }

    public function release(string $name): void
    {
        $this->connection->executeStatement(
            'UPDATE tl_seo_studio_config SET value = ? WHERE name = ?',
            ['0', 'lock:' . $name],
        );
    }
}
