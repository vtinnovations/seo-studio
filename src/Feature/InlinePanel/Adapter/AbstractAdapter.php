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
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Feature\InlinePanel\AdapterInterface;
use VTinnovations\SeoStudio\Feature\InlinePanel\PanelResult;
use VTinnovations\SeoStudio\Feature\InlinePanel\VerdictCache;

/**
 * Shared adapter plumbing: verdict cache + the score/reason/alternatives
 * LLM call with a fixed JSON schema.
 */
abstract class AbstractAdapter implements AdapterInterface
{
    protected const SCORE_SCHEMA = [
        'name' => 'panel_verdict',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'integer', 'description' => '0-100'],
                'reason' => ['type' => 'string', 'description' => 'Ein Satz Begründung'],
                'alternatives' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => '2-3 bessere Alternativen',
                ],
            ],
            'required' => ['score', 'reason', 'alternatives'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        protected readonly AiGateway $ai,
        protected readonly VerdictCache $cache,
    ) {
    }

    /**
     * Cache-wrapped LLM verdict.
     *
     * @param list<array{mime: string, data: string}> $images
     */
    protected function cachedVerdict(
        string $cacheKey,
        string $systemPrompt,
        string $userPrompt,
        string $purpose,
        array $images = [],
    ): PanelResult {
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            model: '',
            temperature: 0.3,
            maxTokens: 600,
            responseSchema: self::SCORE_SCHEMA,
            purpose: $purpose,
            images: $images,
        ));

        $json = $response->asJson() ?? [];
        $result = PanelResult::fromArray($json);

        if ($result->reason === '') {
            $result = new PanelResult(50, 'KI-Antwort unvollständig — bitte erneut prüfen.', []);
        } else {
            $this->cache->put($cacheKey, $result);
        }

        return $result;
    }
}
