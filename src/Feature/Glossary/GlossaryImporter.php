<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Glossary;

use Doctrine\DBAL\Connection;

/**
 * One-click migration from the legacy tahericreate/vtinnovations glossary
 * bundle (tl_glossary_entry) into tl_seo_studio_glossary — so customers can
 * drop the extra bundle. Legacy data is only READ, never modified.
 */
final class GlossaryImporter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly GlossaryGenerator $generator,
    ) {
    }

    /**
     * Number of importable legacy entries, or null when the legacy bundle
     * isn't installed.
     */
    public function countLegacyEntries(): ?int
    {
        try {
            if (!$this->connection->createSchemaManager()->tablesExist(['tl_glossary_entry'])) {
                return null;
            }

            return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tl_glossary_entry');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Imports legacy entries; terms that already exist are skipped.
     * Publish state carries over.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT title, excerpt, content, metaTitle, metaDescription, published FROM tl_glossary_entry',
        );

        $existing = array_map(
            'mb_strtolower',
            $this->connection->fetchFirstColumn('SELECT term FROM tl_seo_studio_glossary'),
        );

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $term = trim((string) $row['title']);
            if ($term === '' || \in_array(mb_strtolower($term), $existing, true)) {
                ++$skipped;
                continue;
            }

            $definition = trim((string) $row['content']);
            if ($definition === '') {
                $definition = trim((string) $row['excerpt']);
            }
            if ($definition === '') {
                ++$skipped;
                continue;
            }

            $this->generator->insertEntry(
                $term,
                $definition,
                (string) $row['published'] === '1',
                trim((string) $row['metaTitle']),
                trim((string) $row['metaDescription']),
            );
            $existing[] = mb_strtolower($term);
            ++$imported;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
