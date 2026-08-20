<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

use Doctrine\DBAL\Connection;

/**
 * LLM verdicts keyed by hash(adapter + relevant content). Same content =
 * same verdict, no repeated token spend. Invalidation is implicit: content
 * change changes the hash.
 */
final class VerdictCache
{
    private const MAX_AGE = 2592000; // 30 days — pruned lazily on write

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function key(string $adapter, string ...$parts): string
    {
        return hash('sha256', $adapter . "\x1e" . implode("\x1e", $parts));
    }

    public function get(string $cacheKey): ?PanelResult
    {
        try {
            $payload = $this->connection->fetchOne(
                'SELECT payload FROM tl_seo_studio_verdict WHERE cacheKey = ?',
                [$cacheKey],
            );
        } catch (\Throwable) {
            return null;
        }

        if (!\is_string($payload)) {
            return null;
        }

        $data = json_decode($payload, true);

        return \is_array($data) ? PanelResult::fromArray($data, true) : null;
    }

    public function put(string $cacheKey, PanelResult $result): void
    {
        $payload = json_encode($result->toArray(), JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        try {
            $updated = $this->connection->update(
                'tl_seo_studio_verdict',
                ['tstamp' => time(), 'payload' => $payload],
                ['cacheKey' => $cacheKey],
            );

            if ($updated === 0) {
                $this->connection->insert('tl_seo_studio_verdict', [
                    'tstamp' => time(),
                    'cacheKey' => $cacheKey,
                    'payload' => $payload,
                ]);
            }

            // Lazy prune — keeps the table bounded without a cron.
            $this->connection->executeStatement(
                'DELETE FROM tl_seo_studio_verdict WHERE tstamp < ?',
                [time() - self::MAX_AGE],
            );
        } catch (\Throwable) {
            // Cache failure must never break the suggestion flow.
        }
    }
}
