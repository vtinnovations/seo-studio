<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Content;

use Doctrine\DBAL\Connection;

/**
 * Resolves the published site roots and the regular pages beneath a chosen
 * root, so the dashboard + analysis can be scoped to one start point instead
 * of the whole install. tl_page has no root column, so the tree is walked in
 * PHP (portable, cycle-safe).
 */
final class RootScope
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    public function roots(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, title FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting",
        );

        return array_map(
            static fn (array $r): array => ['id' => (int) $r['id'], 'title' => (string) $r['title']],
            $rows,
        );
    }

    /**
     * Regular, published page ids under a root (null / 0 = all roots).
     *
     * @return list<int>
     */
    public function pageIds(?int $rootId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, pid, type, published FROM tl_page ORDER BY sorting',
        );

        $childrenByPid = [];
        foreach ($rows as $row) {
            $childrenByPid[(int) $row['pid']][] = $row;
        }

        $out = [];
        $walk = function (int $pid, int $depth) use (&$walk, &$out, $childrenByPid): void {
            if ($depth > 30) {
                return;
            }

            foreach ($childrenByPid[$pid] ?? [] as $page) {
                if ((string) $page['type'] === 'regular' && (int) $page['published'] === 1) {
                    $out[] = (int) $page['id'];
                }
                $walk((int) $page['id'], $depth + 1);
            }
        };

        if ($rootId !== null && $rootId > 0) {
            $walk($rootId, 0);
        } else {
            foreach ($childrenByPid[0] ?? [] as $root) {
                $walk((int) $root['id'], 0);
            }
        }

        return $out;
    }

    /**
     * A safe root id from raw request input: 0 (all) unless it matches a real
     * published root.
     */
    public function sanitize(int $rootId): int
    {
        if ($rootId <= 0) {
            return 0;
        }

        foreach ($this->roots() as $root) {
            if ($root['id'] === $rootId) {
                return $rootId;
            }
        }

        return 0;
    }
}
