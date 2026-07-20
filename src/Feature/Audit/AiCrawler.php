<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

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
                'purpose' => 'Training/Index für Claude',
            ],
            'PerplexityBot' => [
                'label' => 'PerplexityBot',
                'purpose' => 'Perplexity-Antworten mit Quellenangabe',
            ],
            'Google-Extended' => [
                'label' => 'Google-Extended',
                'purpose' => 'Gemini-Training (nicht die Google-Suche!)',
            ],
            'Bingbot' => [
                'label' => 'Bingbot (Microsoft)',
                'purpose' => 'Bing-Index — Grundlage für ChatGPT-Websuche und Copilot',
            ],
        ];
    }
}
