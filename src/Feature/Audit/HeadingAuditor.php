<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use VTinnovations\SeoStudio\Core\Config\Translations;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;
use VTinnovations\SeoStudio\Core\Content\HeadingNode;

/**
 * Deterministic heading-structure audit for one page: H1 count, level skips,
 * hierarchy issues. Empty headlines never reach the extractor, so "empty"
 * means an element whose headline unit exists but holds no text — those are
 * dropped by the extractor by design and reported indirectly via level skips.
 */
final class HeadingAuditor
{
    public function __construct(
        private readonly ContentExtractor $extractor,
    ) {
    }

    /**
     * @return list<array{severity: string, message: string}>
     */
    public function audit(int $pageId): array
    {
        $content = $this->extractor->forPage($pageId);
        $findings = [];

        $h1 = array_values(array_filter($content->headings, static fn (HeadingNode $h): bool => $h->level === 1));

        if (\count($h1) === 0) {
            $findings[] = [
                'severity' => 'info',
                'message' => Translations::text('audit.noH1'),
            ];
        } elseif (\count($h1) > 1) {
            $findings[] = [
                'severity' => 'error',
                'message' => Translations::text('audit.multipleH1', \count($h1), $h1[0]->text),
            ];
        }

        $previousLevel = 0;
        foreach ($content->headings as $heading) {
            if ($previousLevel > 0 && $heading->level > $previousLevel + 1) {
                $findings[] = [
                    'severity' => 'warning',
                    'message' => sprintf(
                        'Ebenen-Sprung: H%d → H%d bei „%s“ (Element-ID %d) — Zwischenebene fehlt.',
                        $previousLevel,
                        $heading->level,
                        $heading->text,
                        $heading->contentElementId,
                    ),
                ];
            }
            $previousLevel = $heading->level;
        }

        if ($content->headings !== [] && $content->wordCount > 300 && \count($content->headings) === 1) {
            $findings[] = [
                'severity' => 'info',
                'message' => Translations::text('audit.needsSubheadings', $content->wordCount),
            ];
        }

        if ($findings === []) {
            $findings[] = ['severity' => 'ok', 'message' => Translations::text('audit.headingsOk')];
        }

        return $findings;
    }
}
