<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel\Adapter;

use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Feature\InlinePanel\ContextResolver;
use VTinnovations\SeoStudio\Feature\InlinePanel\PanelResult;
use VTinnovations\SeoStudio\Feature\InlinePanel\VerdictCache;

/**
 * Link text check for hyperlink elements. "hier klicken"/"mehr" detection is
 * fully deterministic; only the improved alternative comes from the LLM.
 */
final class LinkTextAdapter extends AbstractAdapter
{
    private const VAGUE_PATTERNS = [
        'hier', 'hier klicken', 'klicken sie hier', 'klick hier', 'mehr', 'mehr erfahren',
        'mehr lesen', 'weiter', 'weiterlesen', 'link', 'zum link', 'download', 'jetzt klicken',
        'click here', 'here', 'read more', 'more', 'learn more', 'this link', 'details',
    ];

    public function __construct(
        AiGateway $ai,
        VerdictCache $cache,
        private readonly ContextResolver $context,
    ) {
        parent::__construct($ai, $cache);
    }

    public function getId(): string
    {
        return 'linkText';
    }

    public function getFeatureId(): string
    {
        return 'inlineLinkText';
    }

    public function suggest(string $table, int $rowId, string $value): PanelResult
    {
        $value = trim($value);
        $row = $this->context->contentRow($rowId) ?? [];
        $url = (string) ($row['url'] ?? '');

        if ($value === '') {
            return new PanelResult(0, 'Kein Linktext gesetzt — Screenreader und KI-Crawler sehen nur die URL.', []);
        }

        $normalized = mb_strtolower(trim($value, " .!…»«\"'"));
        $isVague = \in_array($normalized, self::VAGUE_PATTERNS, true);

        if (!$isVague && mb_strlen($value) >= 8) {
            // Deterministically fine — cheap LLM polish only for vague/short texts.
            return new PanelResult(90, 'Aussagekräftiger Linktext.', []);
        }

        $pageTitle = $this->context->pageTitle($this->context->pageIdForContentElement($rowId));
        $cacheKey = $this->cache->key('linkText', $value, $url, $pageTitle);

        $system = 'Du verbesserst Linktexte (Barrierefreiheit + SEO). Antworte über das JSON-Schema. '
            . 'Ein guter Linktext beschreibt das ZIEL des Links, ohne "hier klicken"-Floskeln, 2-6 Wörter. '
            . 'Gib 2-3 Alternativen in der Sprache des Originals.';

        $user = "Linktext: {$value}\n"
            . ($url !== '' ? "Ziel-URL: {$url}\n" : '')
            . ($pageTitle !== '' ? "Seite: {$pageTitle}" : '');

        $result = $this->cachedVerdict($cacheKey, $system, $user, 'panel_link_text');

        // The deterministic verdict wins over whatever score the LLM gave.
        if ($isVague) {
            return new PanelResult(
                min($result->score, 25),
                'Floskel-Linktext („' . $value . '“) — beschreibt das Linkziel nicht.',
                $result->alternatives,
                $result->fromCache,
            );
        }

        return $result;
    }
}
