<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Freshness;

use Doctrine\DBAL\Connection;

/**
 * Freshness monitor: published pages whose newest edit (page or article) is
 * older than the threshold. Pure DB.
 */
final class StalePageFinder
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{id: int, title: string, lastmod: int, ageDays: int}>
     */
    public function find(int $thresholdDays = 14, int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT p.id, p.title, GREATEST(p.tstamp, COALESCE((
                 SELECT MAX(a.tstamp) FROM tl_article a WHERE a.pid = p.id AND a.published = '1'
             ), 0)) AS lastmod
             FROM tl_page p
             WHERE p.type = 'regular' AND p.published = '1'
             HAVING lastmod < ?
             ORDER BY lastmod ASC
             LIMIT " . max(1, min(200, $limit)),
            [time() - $thresholdDays * 86400],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'lastmod' => (int) $row['lastmod'],
            'ageDays' => (int) floor((time() - (int) $row['lastmod']) / 86400),
        ], $rows);
    }
}
