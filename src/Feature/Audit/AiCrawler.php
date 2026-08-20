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

/**
 * Catalogue of AI/answer-engine crawlers worth auditing. Deterministic data,
 * updated with bundle releases.
 */
final class AiCrawler
{
    /**
     * token => [label, purpose (DE), weight] — weight orders the report by impact.
     *
     * @return array<string, array{label: string, purpose: string}>
     */
    public static function catalogue(): array
    {
        return [
            'GPTBot' => [
                'label' => 'GPTBot (OpenAI)',
                'purpose' => 'Training von OpenAI-Modellen (ChatGPT-Wissen)',
            ],
            'OAI-SearchBot' => [
                'label' => 'OAI-SearchBot (OpenAI)',
                'purpose' => 'ChatGPT-Suche — Zitierungen und Quellenlinks',
            ],
            'ClaudeBot' => [
                'label' => 'ClaudeBot (Anthropic)',
                'purpose' => Translations::text('crawler.claude'),
            ],
            'PerplexityBot' => [
                'label' => 'PerplexityBot',
                'purpose' => 'Perplexity-Antworten mit Quellenangabe',
            ],
            'Google-Extended' => [
                'label' => 'Google-Extended',
                'purpose' => Translations::text('crawler.googleExtended'),
            ],
            'Bingbot' => [
                'label' => 'Bingbot (Microsoft)',
                'purpose' => Translations::text('crawler.bingbot'),
            ],
        ];
    }
}
