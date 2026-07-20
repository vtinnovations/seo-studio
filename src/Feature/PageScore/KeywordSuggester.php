<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;

/**
 * Proposes ONE focus keyword for a page from its real content.
 *
 * Deliberately conservative: a single short phrase (the term a visitor would
 * actually type into Google), no list, no stuffing. The editor reviews it
 * before it drives the keyword checks.
 */
final class KeywordSuggester
{
    private const SCHEMA = [
        'name' => 'keyword_result',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'keyword' => ['type' => 'string', 'description' => 'A single focus keyword or short phrase (2-4 words), no quotes'],
            ],
            'required' => ['keyword'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly ContentExtractor $extractor,
    ) {
    }

    /**
     * @throws AiException
     * @throws \RuntimeException when the page has no extractable content
     */
    public function suggest(int $pageId): string
    {
        $content = $this->extractor->forPage($pageId);

        if ($content->isEmpty() && $content->pageTitle === '') {
            throw new \RuntimeException('Die Seite hat keinen extrahierbaren Inhalt — bitte erst Inhalte anlegen.');
        }

        $system = 'Du bist ein SEO-Experte. Du bestimmst das EINE wichtigste Fokus-Keyword einer Seite: '
            . 'der Suchbegriff, den ein Nutzer bei Google eingeben würde, um genau diese Seite zu finden. '
            . 'Antworte ausschließlich über das Tool/JSON-Schema. Sprache: ' . $content->language . '. '
            . 'Regeln: 2-4 Wörter, konkret, keine Marke wenn vermeidbar, kein Satz, keine Anführungszeichen, Kleinschreibung außer Eigennamen.';

        $user = "Seitentitel: {$content->pageTitle}\n"
            . "Gliederung:\n{$content->headingOutline()}\n\n"
            . "Inhalt:\n{$content->truncatedPlaintext(2000)}";

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: '',
            temperature: 0.3,
            maxTokens: 60,
            responseSchema: self::SCHEMA,
            purpose: 'keyword_suggestion',
        ));

        $json = $response->asJson();
        if ($json === null || !\is_string($json['keyword'] ?? null) || trim($json['keyword']) === '') {
            throw new AiException('KI-Antwort war kein gültiges JSON.', AiExceptionKind::InvalidResponse);
        }

        $keyword = trim((string) preg_replace('/\s+/u', ' ', $json['keyword']));
        $keyword = trim($keyword, "\"'“”„ ");

        return mb_substr($keyword, 0, 128);
    }
}
