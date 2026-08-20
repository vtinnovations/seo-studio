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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\InsertTag\InsertTagParser;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Config\ConfigProvider;

/**
 * DB-only page content extraction: published tl_article of a page → visible
 * tl_content (ordered by sorting) → plaintext + heading tree + first
 * paragraph + word count. No HTTP rendering (v1 scope).
 */
final class ContentExtractor
{
    /**
     * Text-bearing fields per element type; "headline" is handled separately
     * because it is a serialized {unit, value} array.
     */
    private const TEXT_FIELDS = ['text', 'html', 'summary', 'code'];

    /**
     * JSON prop keys to ignore in custom (page-builder) elements — links,
     * assets, styling: never prose, only noise for the SEO analysis.
     */
    private const SKIP_PROP_SUFFIX = ['_url', '_href', '_link', '_icon', '_id', '_class', '_css', '_color', '_preset', '_src', '_image', '_img'];

    /** @var array<string, bool> per-request cache of "does tl_content have column X". */
    private array $columnCache = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly InsertTagParser $insertTagParser,
        private readonly ContaoFramework $framework,
        private readonly ConfigProvider $config,
    ) {
    }

    public function forPage(int $pageId): ExtractedContent
    {
        $this->framework->initialize();

        /** @var array<string, mixed> $page */
        $page = $this->connection->fetchAssociative(
            'SELECT id, title, pageTitle, language FROM tl_page WHERE id = ?',
            [$pageId],
        ) ?: [];

        $articleIds = $this->connection->fetchFirstColumn(
            "SELECT id FROM tl_article WHERE pid = ? AND published = '1' ORDER BY sorting",
            [$pageId],
        );

        $headings = [];
        $textBlocks = [];

        if ($articleIds !== []) {
            // Page builders (e.g. Draggo) keep their prose in extra columns —
            // include them only where they exist so the query stays portable.
            $optional = array_filter(
                ['sectionHeadline', 'draggo_props'],
                fn (string $col): bool => $this->hasColumn($col),
            );
            $columns = array_merge(
                ['id', 'pid', 'type', 'headline', 'text', 'html', 'summary', 'listitems', 'tableitems'],
                $optional,
            );

            $elements = $this->connection->fetchAllAssociative(
                'SELECT ' . implode(', ', $columns) . "
                 FROM tl_content
                 WHERE ptable = 'tl_article' AND pid IN (?) AND invisible = ''
                 ORDER BY FIELD(pid, ?), sorting",
                [$articleIds, $articleIds],
                [ArrayParameterType::INTEGER, ArrayParameterType::INTEGER],
            );

            foreach ($elements as $element) {
                $this->extractHeadline($element, $headings, 'headline');
                $this->extractHeadline($element, $headings, 'sectionHeadline');
                $this->extractText($element, $textBlocks);
                $this->extractCustomProps($element, $textBlocks);
            }
        }

        $plaintext = trim(implode("\n\n", array_filter($textBlocks)));

        return new ExtractedContent(
            pageId: $pageId,
            pageTitle: (string) ($page['pageTitle'] ?? '') !== '' ? (string) $page['pageTitle'] : (string) ($page['title'] ?? ''),
            language: $this->resolveLanguage($pageId, (string) ($page['language'] ?? '')),
            plaintext: $plaintext,
            headings: $headings,
            firstParagraph: $this->firstParagraph($textBlocks),
            wordCount: $plaintext === '' ? 0 : \count(preg_split('/\s+/u', $plaintext, -1, PREG_SPLIT_NO_EMPTY) ?: []),
        );
    }

    /**
     * @param array<string, mixed> $element
     * @param list<HeadingNode> $headings
     */
    private function extractHeadline(array $element, array &$headings, string $field = 'headline'): void
    {
        $raw = $element[$field] ?? null;
        if (!\is_string($raw) || $raw === '') {
            return;
        }

        $unit = 'h2';
        $value = $raw;

        if (str_starts_with($raw, 'a:')) {
            $decoded = @unserialize($raw, ['allowed_classes' => false]);
            if (\is_array($decoded)) {
                $unit = (string) ($decoded['unit'] ?? 'h2');
                $value = (string) ($decoded['value'] ?? '');
            }
        }

        $value = $this->toPlaintext($value);
        if ($value === '') {
            return;
        }

        $level = (int) substr($unit, 1);
        if ($level < 1 || $level > 6) {
            return;
        }

        $headings[] = new HeadingNode($level, $value, (int) $element['id']);
    }

    /**
     * @param array<string, mixed> $element
     * @param list<string> $textBlocks
     */
    private function extractText(array $element, array &$textBlocks): void
    {
        foreach (self::TEXT_FIELDS as $field) {
            $value = $element[$field] ?? null;
            if (\is_string($value) && trim($value) !== '') {
                $plain = $this->toPlaintext($value);
                if ($plain !== '') {
                    $textBlocks[] = $plain;
                }
            }
        }

        // Serialized list/table items
        foreach (['listitems', 'tableitems'] as $field) {
            $value = $element[$field] ?? null;
            if (!\is_string($value) || $value === '') {
                continue;
            }

            $decoded = @unserialize($value, ['allowed_classes' => false]);
            if (!\is_array($decoded)) {
                continue;
            }

            $items = [];
            array_walk_recursive($decoded, function (mixed $item) use (&$items): void {
                if (\is_string($item) && trim($item) !== '') {
                    $items[] = $this->toPlaintext($item);
                }
            });

            if ($items !== []) {
                $textBlocks[] = implode("\n", array_filter($items));
            }
        }
    }

    /**
     * Extracts prose from a page-builder element's JSON prop bag (e.g. Draggo's
     * `draggo_props`): all string values whose key is not a link/asset/style,
     * recursing into arrays of strings. Everything becomes body plaintext so it
     * feeds word count, keyword-in-text and readability.
     *
     * @param array<string, mixed> $element
     * @param list<string> $textBlocks
     */
    private function extractCustomProps(array $element, array &$textBlocks): void
    {
        $raw = $element['draggo_props'] ?? null;
        if (!\is_string($raw) || trim($raw) === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            return;
        }

        $collected = [];
        foreach ($decoded as $key => $value) {
            if (!\is_string($key) || $this->isSkippableProp($key)) {
                continue;
            }

            if (\is_string($value)) {
                $this->collectPropString($value, $collected);
            } elseif (\is_array($value)) {
                array_walk_recursive($value, function (mixed $item) use (&$collected): void {
                    if (\is_string($item)) {
                        $this->collectPropString($item, $collected);
                    }
                });
            }
        }

        if ($collected !== []) {
            $textBlocks[] = implode("\n", $collected);
        }
    }

    /**
     * @param list<string> $collected
     */
    private function collectPropString(string $value, array &$collected): void
    {
        $value = trim($value);
        // Skip empties, anchors, URLs and single tokens (labels/keys).
        if ($value === '' || $value[0] === '#' || preg_match('#^(https?:|mailto:|tel:|/)#i', $value)) {
            return;
        }

        $plain = $this->toPlaintext($value);
        if ($plain !== '') {
            $collected[] = $plain;
        }
    }

    private function isSkippableProp(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SKIP_PROP_SUFFIX as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function hasColumn(string $column): bool
    {
        if (isset($this->columnCache[$column])) {
            return $this->columnCache[$column];
        }

        try {
            $columns = $this->connection->createSchemaManager()->listTableColumns('tl_content');
            $exists = isset($columns[strtolower($column)]);
        } catch (\Throwable) {
            $exists = false;
        }

        return $this->columnCache[$column] = $exists;
    }

    private function toPlaintext(string $html): string
    {
        try {
            $html = $this->insertTagParser->replaceInline($html);
        } catch (\Throwable) {
            // Unresolvable insert tag — keep raw text, strip the tags below.
        }

        // Block-level tags become newlines so words don't fuse.
        $html = preg_replace('/<(\/?(p|div|br|li|h[1-6]|tr|td|th)[^>]*)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Drop leftover insert tags and normalize whitespace.
        $text = preg_replace('/\{\{[^}]+\}\}/', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @param list<string> $textBlocks
     */
    private function firstParagraph(array $textBlocks): string
    {
        foreach ($textBlocks as $block) {
            foreach (explode("\n", $block) as $line) {
                $line = trim($line);
                // Skip ultra-short lines (labels, buttons) — a real paragraph.
                if (mb_strlen($line) >= 40) {
                    return $line;
                }
            }
        }

        return '';
    }

    /**
     * Language: settings override → page language → root page language → 'de'.
     */
    private function resolveLanguage(int $pageId, string $pageLanguage): string
    {
        $override = trim((string) $this->config->get('languageOverride', ''));
        if ($override !== '') {
            return $override;
        }

        if ($pageLanguage !== '') {
            return $pageLanguage;
        }

        // Walk up to the root page (max 25 levels, cycle-safe).
        $currentId = $pageId;
        for ($i = 0; $i < 25; ++$i) {
            $row = $this->connection->fetchAssociative(
                'SELECT pid, type, language FROM tl_page WHERE id = ?',
                [$currentId],
            );

            if ($row === false) {
                break;
            }

            if (($row['type'] ?? '') === 'root') {
                return (string) $row['language'] !== '' ? (string) $row['language'] : 'de';
            }

            $currentId = (int) $row['pid'];
            if ($currentId === 0) {
                break;
            }
        }

        return 'de';
    }
}
