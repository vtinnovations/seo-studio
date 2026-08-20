<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Optimize;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;

/**
 * Resolves the editorial context around ANY text/headline field so the
 * optimizer can rewrite with real page context and generate from scratch
 * when a field is empty.
 *
 * Works for the standard content tables (tl_content on a page article,
 * tl_news, tl_calendar_events). tl_content covers all content-element based
 * bundles (Draggo etc.) automatically.
 */
final class PageContextResolver
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ContentExtractor $extractor,
    ) {
    }

    /**
     * @return array{
     *     pageTitle: string,
     *     siteName: string,
     *     language: string,
     *     plaintext: string,
     *     focusKeyword: string,
     *     siblingHeadlines: list<string>
     * }
     */
    public function resolve(string $table, int $rowId): array
    {
        $pageId = $this->pageIdFor($table, $rowId);

        if ($pageId !== null) {
            $extracted = $this->extractor->forPage($pageId);

            return [
                'pageTitle' => $extracted->pageTitle,
                'siteName' => $this->siteName($pageId),
                'language' => $extracted->language,
                'plaintext' => $extracted->truncatedPlaintext(2500),
                'focusKeyword' => $this->focusKeyword($pageId),
                'siblingHeadlines' => array_map(
                    static fn ($h): string => $h->text,
                    $extracted->headings,
                ),
            ];
        }

        // News / events: use the entry's own headline + body as context.
        [$headline, $body, $language] = $this->entryContext($table, $rowId);

        return [
            'pageTitle' => $headline,
            'siteName' => $this->firstRootTitle(),
            'language' => $language,
            'plaintext' => $body,
            'focusKeyword' => '',
            'siblingHeadlines' => [],
        ];
    }

    /**
     * The page's focus keyword (per-page SEO score feature); '' when unset or
     * when that feature was never migrated.
     */
    private function focusKeyword(int $pageId): string
    {
        try {
            $value = $this->connection->fetchOne('SELECT seoFocusKeyword FROM tl_page WHERE id = ?', [$pageId]);
        } catch (\Throwable) {
            return '';
        }

        return \is_string($value) ? trim($value) : '';
    }

    /**
     * The owning page id when the row is a content element on a page article.
     */
    private function pageIdFor(string $table, int $rowId): ?int
    {
        // A FAQ entry belongs to a page — reuse that page's full context.
        if ($table === 'tl_seo_studio_faq') {
            $pid = $this->connection->fetchOne('SELECT pid FROM tl_seo_studio_faq WHERE id = ?', [$rowId]);

            return $pid !== false && (int) $pid > 0 ? (int) $pid : null;
        }

        if ($table !== 'tl_content') {
            return null;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT pid, ptable FROM tl_content WHERE id = ?',
            [$rowId],
        );

        if ($row === false || ($row['ptable'] !== '' && $row['ptable'] !== 'tl_article')) {
            return null;
        }

        $pageId = $this->connection->fetchOne('SELECT pid FROM tl_article WHERE id = ?', [(int) $row['pid']]);

        return $pageId === false ? null : (int) $pageId;
    }

    /**
     * @return array{0: string, 1: string, 2: string} headline, body plaintext, language
     */
    private function entryContext(string $table, int $rowId): array
    {
        // Glossary entries have no page — the term and its definition ARE the context.
        if ($table === 'tl_seo_studio_glossary') {
            $row = $this->connection->fetchAssociative(
                'SELECT term, definition FROM tl_seo_studio_glossary WHERE id = ?',
                [$rowId],
            );

            return [
                \is_array($row) ? trim((string) $row['term']) : '',
                \is_array($row) ? mb_substr(trim(strip_tags((string) $row['definition'])), 0, 3000) : '',
                $this->firstRootLanguage(),
            ];
        }

        if (!\in_array($table, ['tl_news', 'tl_calendar_events'], true)) {
            return ['', '', $this->firstRootLanguage()];
        }

        $titleColumn = $table === 'tl_news' ? 'headline' : 'title';
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s AS headline, teaser FROM %s WHERE id = ?', $titleColumn, $table),
            [$rowId],
        );

        $headline = \is_array($row) ? trim((string) $row['headline']) : '';
        $body = \is_array($row) ? trim(strip_tags((string) $row['teaser'])) : '';

        $texts = $this->connection->fetchFirstColumn(
            "SELECT text FROM tl_content WHERE ptable = ? AND pid = ? AND invisible = '' AND text IS NOT NULL ORDER BY sorting LIMIT 5",
            [$table, $rowId],
        );
        $body = trim($body . "\n" . strip_tags(implode("\n", array_map('strval', $texts))));

        return [$headline, mb_substr($body, 0, 3000), $this->firstRootLanguage()];
    }

    private function siteName(int $pageId): string
    {
        $currentId = $pageId;
        for ($i = 0; $i < 25; ++$i) {
            $row = $this->connection->fetchAssociative('SELECT pid, type, title FROM tl_page WHERE id = ?', [$currentId]);
            if ($row === false) {
                break;
            }
            if (($row['type'] ?? '') === 'root') {
                return (string) $row['title'];
            }
            $currentId = (int) $row['pid'];
            if ($currentId === 0) {
                break;
            }
        }

        return $this->firstRootTitle();
    }

    private function firstRootTitle(): string
    {
        $title = $this->connection->fetchOne("SELECT title FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting LIMIT 1");

        return \is_string($title) ? $title : '';
    }

    private function firstRootLanguage(): string
    {
        $language = $this->connection->fetchOne("SELECT language FROM tl_page WHERE type = 'root' AND published = '1' ORDER BY sorting LIMIT 1");

        return \is_string($language) && $language !== '' ? $language : 'de';
    }
}
