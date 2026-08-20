<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Faq;

use Doctrine\DBAL\Connection;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Content\ContentExtractor;

/**
 * Generates FAQ candidates from real page content. Inserted UNPUBLISHED —
 * editors curate and publish (never silently public). Questions use real
 * user phrasing (AEO: answer engines cite question/answer pairs).
 */
final class FaqGenerator
{
    private const SCHEMA = [
        'name' => 'faq_result',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'faqs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'answer' => ['type' => 'string'],
                        ],
                        'required' => ['question', 'answer'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['faqs'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly ContentExtractor $extractor,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return int number of created (unpublished) FAQ rows
     */
    public function generateForPage(int $pageId, int $count = 5): int
    {
        $count = min(10, max(1, $count));

        $content = $this->extractor->forPage($pageId);
        if ($content->isEmpty()) {
            throw new \RuntimeException('Die Seite hat keinen extrahierbaren Inhalt — FAQ-Generierung braucht Text.');
        }

        $existing = $this->connection->fetchFirstColumn(
            'SELECT question FROM tl_seo_studio_faq WHERE pid = ?',
            [$pageId],
        );

        $system = 'Du erstellst FAQ (häufige Fragen + Antworten) aus Webseiten-Inhalten. Antworte über das JSON-Schema. '
            . 'Regeln: Fragen so formulieren, wie Nutzer sie wirklich stellen (natürliche Sprache, W-Fragen). '
            . 'Antworten: 2-4 Sätze, erste Aussage beantwortet die Frage direkt, NUR Fakten aus dem gegebenen Inhalt — nichts erfinden. '
            . 'Sprache: ' . $content->language . '. Genau ' . $count . ' Paare.';

        $user = "Seite: {$content->pageTitle}\n"
            . ($existing !== [] ? "Bereits vorhandene Fragen (NICHT wiederholen):\n- " . implode("\n- ", array_map('strval', $existing)) . "\n" : '')
            . "Inhalt:\n" . $content->truncatedPlaintext(3000);

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: '',
            temperature: 0.4,
            maxTokens: 2000,
            responseSchema: self::SCHEMA,
            purpose: 'faq_generation',
        ));

        $json = $response->asJson();
        $faqs = \is_array($json) && \is_array($json['faqs'] ?? null) ? $json['faqs'] : [];

        $sorting = (int) $this->connection->fetchOne(
            'SELECT COALESCE(MAX(sorting), 0) FROM tl_seo_studio_faq WHERE pid = ?',
            [$pageId],
        );

        $created = 0;
        foreach ($faqs as $faq) {
            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            if ($question === '' || $answer === '' || \in_array($question, array_map('strval', $existing), true)) {
                continue;
            }

            $sorting += 64;
            $this->connection->insert('tl_seo_studio_faq', [
                'tstamp' => time(),
                'sorting' => $sorting,
                'pid' => $pageId,
                'question' => mb_substr($question, 0, 255),
                'answer' => '<p>' . htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
                'published' => '', // editors curate + publish
            ]);
            ++$created;
        }

        return $created;
    }
}
