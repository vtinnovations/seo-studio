<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use Doctrine\DBAL\Connection;

/**
 * Site-wide duplicate detection: identical pageTitle or description on more
 * than one published page. Pure DB, no LLM.
 */
final class DuplicateChecker
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{field: string, value: string, pages: list<array{id: int, title: string}>}>
     */
    public function findAll(): array
    {
        $result = [];

        foreach (['pageTitle', 'description'] as $field) {
            $rows = $this->connection->fetchAllAssociative(
                "SELECT {$field} AS value, GROUP_CONCAT(id) AS ids, GROUP_CONCAT(title SEPARATOR '\x1e') AS titles
                 FROM tl_page
                 WHERE published = '1' AND type = 'regular' AND {$field} != '' AND {$field} IS NOT NULL
                 GROUP BY {$field} HAVING COUNT(*) > 1",
            );

            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', (string) $row['ids']));
                $titles = explode("\x1e", (string) $row['titles']);

                $pages = [];
                foreach ($ids as $i => $id) {
                    $pages[] = ['id' => $id, 'title' => (string) ($titles[$i] ?? '')];
                }

                $result[] = [
                    'field' => $field,
                    'value' => (string) $row['value'],
                    'pages' => $pages,
                ];
            }
        }

        return $result;
    }

    /**
     * Duplicates of ONE page's meta values (for the on-save warning).
     *
     * @return list<string> warning messages
     */
    public function forPage(int $pageId): array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pageTitle, description FROM tl_page WHERE id = ?',
            [$pageId],
        );

        if ($row === false) {
            return [];
        }

        $warnings = [];

        foreach (['pageTitle' => 'Seitentitel', 'description' => 'Beschreibung'] as $field => $label) {
            $value = trim((string) $row[$field]);
            if ($value === '') {
                continue;
            }

            $others = $this->connection->fetchAllAssociative(
                "SELECT id, title FROM tl_page WHERE {$field} = ? AND id != ? AND published = '1' LIMIT 3",
                [$value, $pageId],
            );

            foreach ($others as $other) {
                $warnings[] = sprintf(
                    'SEO Studio: %s ist identisch mit Seite „%s“ (ID %d) — Duplikate schwächen beide Seiten im Ranking.',
                    $label,
                    (string) $other['title'],
                    (int) $other['id'],
                );
            }
        }

        return $warnings;
    }
}
