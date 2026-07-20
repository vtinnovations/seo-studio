<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Optimize;

use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Feature\InlinePanel\PanelResult;
use VTinnovations\SeoStudio\Feature\InlinePanel\VerdictCache;

/**
 * Global SEO optimizer for headline and text fields.
 *
 * Three modes, one LLM call each:
 *   - score:    rate the current value (0-100 + reason + alternatives)
 *   - rewrite:  SEO-optimize the existing value, keeping its meaning
 *   - generate: field is empty → write it from the page's real content
 *
 * fieldType headline (short, punchy, answer-first) vs text (flowing HTML
 * paragraphs). The page context (plaintext, page/site name, sibling
 * headlines) grounds both rewrite and generate so nothing is invented.
 */
final class TextOptimizer
{
    private const SCORE_SCHEMA = [
        'name' => 'optimize_score',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'integer', 'description' => '0-100'],
                'reason' => ['type' => 'string', 'description' => 'Ein Satz Begründung'],
                'alternatives' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '2-3 bessere Varianten'],
            ],
            'required' => ['score', 'reason', 'alternatives'],
            'additionalProperties' => false,
        ],
    ];

    private const REWRITE_SCHEMA = [
        'name' => 'optimize_rewrite',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'rewrite' => ['type' => 'string', 'description' => 'Die optimierte Fassung'],
                'reason' => ['type' => 'string', 'description' => 'Ein Satz, was verbessert wurde'],
            ],
            'required' => ['rewrite', 'reason'],
            'additionalProperties' => false,
        ],
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly VerdictCache $cache,
        private readonly PageContextResolver $context,
    ) {
    }

    /**
     * @param 'headline'|'text' $fieldType
     * @param 'score'|'rewrite'|'generate' $mode
     */
    public function run(string $table, int $rowId, string $fieldType, string $mode, string $value): PanelResult
    {
        $plainValue = trim(strip_tags($value));

        // Empty field always generates, regardless of requested mode.
        if ($plainValue === '') {
            $mode = 'generate';
        }

        $ctx = $this->context->resolve($table, $rowId);

        return match ($mode) {
            'rewrite' => $this->rewrite($fieldType, $value, $ctx, false),
            'generate' => $this->rewrite($fieldType, '', $ctx, true),
            default => $this->score($table, $rowId, $fieldType, $value, $ctx),
        };
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function score(string $table, int $rowId, string $fieldType, string $value, array $ctx): PanelResult
    {
        $plain = trim(strip_tags($value));
        $siblings = $fieldType === 'headline' ? (array) $ctx['siblingHeadlines'] : [];

        $cacheKey = $this->cache->key('optimize_score', $fieldType, $plain, (string) $ctx['pageTitle']);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $langRule = ' Begründung UND Alternativen in derselben Sprache wie der bewertete Text.';
        $system = $fieldType === 'headline'
            ? 'Du bewertest Überschriften (SEO + AEO). Antworte über das JSON-Schema. Kriterien: konkret, enthält das Thema, '
                . 'gute Länge (<70 Zeichen), aktiv, ggf. Frageform (KI-Suchmaschinen zitieren Fragen). '
                . '2-3 Alternativen; mind. eine als Frage, wenn sinnvoll.' . $langRule
            : 'Du bewertest Fließtexte für SEO/AEO. Antworte über das JSON-Schema. Kriterien: erste Aussage beantwortet '
                . 'das Thema direkt (Antwort-zuerst), klare Struktur, konkrete Fakten, keine Floskeln, gute Lesbarkeit. '
                . '2-3 kurze Verbesserungshinweise als "alternatives".' . $langRule;

        $user = "Sprache: {$ctx['language']}\n"
            . ($ctx['pageTitle'] !== '' ? "Seite: {$ctx['pageTitle']}\n" : '')
            . ($siblings !== [] ? "Andere Überschriften der Seite:\n- " . implode("\n- ", \array_slice(array_map('strval', $siblings), 0, 12)) . "\n" : '')
            . "\nZu bewerten ({$fieldType}):\n{$plain}";

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: '',
            temperature: 0.3,
            maxTokens: 600,
            responseSchema: self::SCORE_SCHEMA,
            purpose: 'optimize_score_' . $fieldType,
        ));

        $result = PanelResult::fromArray($response->asJson() ?? []);
        if ($result->reason === '') {
            return new PanelResult(50, 'KI-Antwort unvollständig — bitte erneut prüfen.', []);
        }

        $this->cache->put($cacheKey, $result);

        return $result;
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function rewrite(string $fieldType, string $value, array $ctx, bool $generate): PanelResult
    {
        if ($generate && trim((string) $ctx['plaintext']) === '' && trim((string) $ctx['pageTitle']) === '') {
            throw new AiException(
                'Zu wenig Kontext auf der Seite, um Inhalt zu erzeugen — bitte erst Text ergänzen.',
                AiExceptionKind::BadRequest,
            );
        }

        if ($fieldType === 'headline') {
            $system = $generate
                ? 'Du schreibst eine SEO-Überschrift für einen Seitenabschnitt, basierend auf dem Seiteninhalt und '
                    . 'dem Seitennamen. Antworte über das JSON-Schema. Kurz (<70 Zeichen), konkret, enthält das Thema, '
                    . 'aktiv; Frageform wenn passend. Kein Markdown, keine Anführungszeichen.'
                : 'Du optimierst eine bestehende Überschrift für SEO/AEO, ohne die Kernaussage zu ändern. '
                    . 'Antworte über das JSON-Schema. Kurz (<70 Zeichen), konkret, aktiv. Kein Markdown, keine Anführungszeichen.';
        } else {
            $system = $generate
                ? 'Du schreibst einen SEO-optimierten Fließtext für einen Seitenabschnitt, basierend AUSSCHLIESSLICH auf '
                    . 'dem gegebenen Seiteninhalt und Seitennamen — erfinde keine Fakten. Antworte über das JSON-Schema. '
                    . 'Erster Satz beantwortet das Thema direkt (Antwort-zuerst). Gib gültiges HTML (nur <p>, <strong>, <ul>, <li>).'
                : 'Du optimierst einen bestehenden Text für SEO/AEO, ohne Fakten zu ändern oder zu erfinden. '
                    . 'Antworte über das JSON-Schema. Erster Satz beantwortet das Thema direkt (Antwort-zuerst), '
                    . 'klare Absätze, konkrete Formulierungen. Gib gültiges HTML (nur <p>, <strong>, <ul>, <li>).';
        }

        // Rewrite keeps the existing text's language; generate uses the page language.
        $system .= $generate
            ? ' Sprache: ' . $ctx['language'] . '.'
            : ' Schreibe in derselben Sprache wie die aktuelle Fassung.';

        $user = ($ctx['siteName'] !== '' ? "Website: {$ctx['siteName']}\n" : '')
            . ($ctx['pageTitle'] !== '' ? "Seite/Abschnitt: {$ctx['pageTitle']}\n" : '')
            . ($generate ? '' : "Aktuelle Fassung:\n" . trim(strip_tags($value)) . "\n\n")
            . "Seiteninhalt als Kontext:\n" . mb_substr((string) $ctx['plaintext'], 0, 3500);

        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: '',
            temperature: 0.5,
            maxTokens: $fieldType === 'headline' ? 300 : 1200,
            responseSchema: self::REWRITE_SCHEMA,
            purpose: ($generate ? 'optimize_generate_' : 'optimize_rewrite_') . $fieldType,
        ));

        $json = $response->asJson() ?? [];
        $rewrite = trim((string) ($json['rewrite'] ?? ''));

        if ($rewrite === '') {
            throw new AiException('KI lieferte keinen Text.', AiExceptionKind::InvalidResponse);
        }

        // Headlines are plain; strip any stray tags/quotes the model added.
        if ($fieldType === 'headline') {
            $rewrite = trim(strip_tags($rewrite), " \"'");
        }

        return new PanelResult(
            score: 0,
            reason: trim((string) ($json['reason'] ?? '')),
            alternatives: [],
            rewrite: $rewrite,
        );
    }
}
