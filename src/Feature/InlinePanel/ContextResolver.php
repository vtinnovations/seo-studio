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
 * Resolves editorial context around a DCA row: the owning page of a content
 * element, sibling headlines (duplicate avoidance, hierarchy), language.
 */
final class ContextResolver
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contentRow(int $contentId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM tl_content WHERE id = ?',
            [$contentId],
        );

        return $row === false ? null : $row;
    }

    public function pageIdForContentElement(int $contentId): ?int
    {
        $row = $this->connection->fetchAssociative(
            'SELECT pid, ptable FROM tl_content WHERE id = ?',
            [$contentId],
        );

        if ($row === false || ($row['ptable'] !== '' && $row['ptable'] !== 'tl_article')) {
            return null;
        }

        $pageId = $this->connection->fetchOne('SELECT pid FROM tl_article WHERE id = ?', [(int) $row['pid']]);

        return $pageId === false ? null : (int) $pageId;
    }

    /**
     * Headlines of all OTHER visible elements on the same page.
     *
     * @return list<string>
     */
    public function siblingHeadlines(int $contentId): array
    {
        $pageId = $this->pageIdForContentElement($contentId);
        if ($pageId === null) {
            return [];
        }

        $rows = $this->connection->fetchFirstColumn(
            "SELECT c.headline FROM tl_content c
             JOIN tl_article a ON a.id = c.pid AND c.ptable IN ('', 'tl_article')
             WHERE a.pid = ? AND a.published = '1' AND c.id != ? AND c.invisible = '' AND c.headline != ''
             ORDER BY a.sorting, c.sorting",
            [$pageId, $contentId],
        );

        $headlines = [];
        foreach ($rows as $raw) {
            $value = (string) $raw;
            if (str_starts_with($value, 'a:')) {
                $decoded = @unserialize($value, ['allowed_classes' => false]);
                $value = \is_array($decoded) ? (string) ($decoded['value'] ?? '') : '';
            }
            $value = trim(strip_tags($value));
            if ($value !== '') {
                $headlines[] = $value;
            }
        }

        return $headlines;
    }

    public function pageTitle(?int $pageId): string
    {
        if ($pageId === null) {
            return '';
        }

        $title = $this->connection->fetchOne('SELECT title FROM tl_page WHERE id = ?', [$pageId]);

        return \is_string($title) ? $title : '';
    }
}
