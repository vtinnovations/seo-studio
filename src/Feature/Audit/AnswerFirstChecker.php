<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;
use VTinnovations\SeoStudio\Feature\InlinePanel\PanelResult;
use VTinnovations\SeoStudio\Feature\InlinePanel\VerdictCache;

/**
 * Answer-first check: does the first paragraph answer the page's core
 * question directly (definition-first)? Answer engines quote pages whose
 * intro IS the answer. Verdict cached by content hash.
 */
final class AnswerFirstChecker
{
    private const SCHEMA = [
        'name' => 'answer_first',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'integer', 'description' => '0-100: beantwortet der Einstieg das Thema direkt?'],
                'reason' => ['type' => 'string'],
                'alternatives' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => '1 umgeschriebener Antwort-zuerst-Einstieg',
                ],
            ],
            'required' => ['score', 'reason', 'alternatives'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly ContentExtractor $extractor,
        private readonly VerdictCache $cache,
    ) {
    }

    public function check(int $pageId): PanelResult
    {
        $content = $this->extractor->forPage($pageId);

        if ($content->firstParagraph === '') {
            return new PanelResult(0, 'Kein Einstiegsabsatz gefunden — die Seite braucht einen Textabsatz am Anfang.', []);
        }

        $cacheKey = $this->cache->key('answerFirst', $content->pageTitle, $content->firstParagraph);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: 'Du prüfst, ob der erste Absatz einer Webseite das Seitenthema DIREKT beantwortet '
                . '(Definition/Kernaussage zuerst, wie ein gutes Lexikon). KI-Suchmaschinen zitieren solche Absätze. '
                . 'Antworte über das JSON-Schema. Liefere GENAU EINE umgeschriebene Antwort-zuerst-Version des Absatzes '
                . '(nur Fakten aus dem Original, Sprache beibehalten).',
            userPrompt: "Seitenthema: {$content->pageTitle}\n\nErster Absatz:\n{$content->firstParagraph}",
            model: '',
            temperature: 0.3,
            maxTokens: 600,
            responseSchema: self::SCHEMA,
            purpose: 'answer_first_check',
        ));

        $result = PanelResult::fromArray($response->asJson() ?? []);
        if ($result->reason !== '') {
            $this->cache->put($cacheKey, $result);
        }

        return $result;
    }
}
