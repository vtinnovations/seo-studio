<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Optimize;

use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Ai\AiGateway;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Feature\InlinePanel\PanelResult;

/**
 * Global SEO optimizer for headline and text fields.
 *
 * DIVISION OF LABOUR: {@see FieldScorer} measures (deterministic PHP), the LLM
 * only writes. A model never grades its own output, so the score is stable and
 * — because no two criteria contradict each other — 100/100 is genuinely
 * reachable.
 *
 * Modes:
 *   - score:    deterministic verdict + one LLM call for the semantic judgement.
 *               Deliberately NO example variants — the checklist states what is
 *               missing and "Mit KI optimieren" writes the fix when asked, so
 *               throwaway suggestions would only burn tokens.
 *   - rewrite:  LLM proposal, measured afterwards, retried with the concrete
 *               violations until it passes or the attempt budget runs out
 *   - generate: same, but written from the page's real content
 */
final class TextOptimizer
{
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

    /**
     * The semantic half of the verdict. PHP can measure form (length, variety,
     * repetition) but never MEANING — whether the sentences belong together,
     * match the page's subject and actually say something. The model answers
     * yes/no on exactly those three points; it never assigns a number, because
     * models are bad at numbers and good at judgements.
     */
    private const SEMANTIC_SCHEMA = [
        'name' => 'semantic_check',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'coherent' => ['type' => 'boolean', 'description' => 'Roter Faden: gehören die Aussagen inhaltlich zusammen?'],
                'onTopic' => ['type' => 'boolean', 'description' => 'Passt der Inhalt zum Seitenthema?'],
                'substantial' => ['type' => 'boolean', 'description' => 'Konkrete, informative Aussagen statt Geschwafel?'],
                'issue' => ['type' => 'string', 'description' => 'Ein kurzer Satz, was inhaltlich fehlt (leer wenn alles erfüllt)'],
            ],
            'required' => ['coherent', 'onTopic', 'substantial', 'issue'],
            'additionalProperties' => false,
        ],
    ];

    /** LLM attempts per rewrite before the best result is returned. */
    private const MAX_ATTEMPTS = 3;

    /**
     * The rules, worded exactly as {@see FieldScorer} measures them, so the
     * model optimises against the real yardstick.
     */
    private const RULES = [
        'headline' => "1. Länge 30-65 Zeichen (harte Grenze).\n"
            . "2. Benennt das konkrete Thema — keine Floskeln wie „Unsere Leistungen“, „Über uns“, „Willkommen“.\n"
            . "3. Aktive Sprache: keine Passivformen (wird/werden/wurde).\n"
            . "4. Entweder Frageform ODER eine Aussage mit mindestens vier bedeutungstragenden Wörtern — beides zählt als erfüllt.\n"
            . "5. Nicht identisch mit einer anderen Überschrift der Seite.\n"
            . '6. Kein Markdown, kein HTML, keine Anführungszeichen.',
        'text' => "1. Mindestens 40 Wörter — darunter zählt der Text als Stummel.\n"
            . "2. Zielumfang 120 Wörter oder mehr — mit echtem Inhalt. Kein Satz darf sich wiederholen, "
            . "und der Wortschatz muss abwechslungsreich sein (Füllen durch Wiederholung wird erkannt und abgewertet).\n"
            . "3. Ab 120 Wörtern in mehrere <p>-Absätze gliedern.\n"
            . "4. Der erste Satz beantwortet das Thema direkt und hat höchstens 25 Wörter.\n"
            . "5. Durchschnittlich höchstens 18 Wörter pro Satz.\n"
            . "6. Höchstens jeder vierte Satz mit Passivform.\n"
            . "7. Keine Floskeln („In der heutigen Zeit“, „Willkommen“).\n"
            . "8. Gut verständlich schreiben: kurze, gebräuchliche Wörter statt langer Komposita, "
            . "keine verschachtelten Nebensatzketten (Lesbarkeitswert mindestens 40 von 100).\n"
            . "9. Mindestens jeder sechste Satz beginnt mit oder enthält ein Übergangswort "
            . "(„außerdem“, „daher“, „zunächst“, „allerdings“, „beispielsweise“), damit der Text argumentiert statt aufzählt.\n"
            . '10. Nur die HTML-Tags <p>, <strong>, <em>, <ul>, <ol>, <li>. Keine erfundenen Fakten.',
    ];

    public function __construct(
        private readonly AiGateway $ai,
        private readonly PageContextResolver $context,
        private readonly FieldScorer $scorer,
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
            default => $this->score($fieldType, $value, $ctx),
        };
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function score(string $fieldType, string $value, array $ctx): PanelResult
    {
        $plain = trim(strip_tags($value));
        $keyword = trim((string) ($ctx['focusKeyword'] ?? ''));
        $siblings = $fieldType === 'headline' ? $this->otherHeadlines($ctx, $plain) : [];

        // Form (PHP) + meaning (LLM) — neither alone is enough.
        $measured = $this->scorer->score(
            $fieldType,
            $value,
            $keyword,
            $siblings,
            $this->semanticChecks($fieldType, $plain, $ctx),
        );
        // No example variants: the checklist already says what is missing, and
        // "Mit KI optimieren" writes the improved version on demand. Generating
        // three throwaway suggestions on every check burns tokens for nothing.
        return new PanelResult($measured['score'], $this->explain($measured), [], false, '', $measured['checks']);
    }

    /**
     * Asks the model for three yes/no judgements about MEANING and turns them
     * into weighted criteria. Returns [] when the text is too short to judge or
     * the model is unavailable — the form-based verdict then stands alone.
     *
     * @param array<string, mixed> $ctx
     * @return list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap?: int|null, grade?: string|null}>
     */
    private function semanticChecks(string $fieldType, string $plain, array $ctx): array
    {
        if (mb_strlen($plain) < 15) {
            return [];
        }

        $system = 'Du prüfst AUSSCHLIESSLICH den Inhalt, niemals Form, Länge oder Rechtschreibung. '
            . "Antworte über das JSON-Schema mit drei Ja/Nein-Urteilen:\n"
            . "- coherent: Haben die Sätze einen roten Faden und gehören inhaltlich zusammen? Zusammenhanglos "
            . "aneinandergereihte Aussagen oder Themensprünge sind NICHT kohärent.\n"
            . "- onTopic: Passt der Inhalt zum angegebenen Seitenthema?\n"
            . "- substantial: Enthält er konkrete, informative Aussagen statt Floskeln, Gerede oder Fantasie?\n"
            . 'Sei streng und ehrlich. „issue“: ein kurzer Satz auf Deutsch, was inhaltlich nicht stimmt (leer, wenn alles erfüllt).';

        $user = 'Seitenthema: ' . ($ctx['pageTitle'] !== '' ? $ctx['pageTitle'] : '(unbekannt)') . "\n"
            . ($fieldType === 'headline' ? "Zu prüfende Überschrift:\n" : "Zu prüfender Text:\n")
            . mb_substr($plain, 0, 3000);

        try {
            $response = $this->ai->complete(new PromptBundle(
                systemPrompt: $system,
                userPrompt: $user,
                model: '',
                temperature: 0.0,
                maxTokens: 300,
                responseSchema: self::SEMANTIC_SCHEMA,
                purpose: 'optimize_semantic_' . $fieldType,
            ));
        } catch (\Throwable) {
            return []; // no AI → form-only verdict, honestly reported
        }

        $json = $response->asJson();
        if (!\is_array($json) || !\array_key_exists('coherent', $json)) {
            return [];
        }

        $issue = trim((string) ($json['issue'] ?? ''));

        // These are KNOCK-OUT criteria: perfect form cannot rescue a text that
        // says nothing or belongs on another page, so a failure caps the score.
        return [
            [
                'label' => 'Roter Faden',
                'ok' => (bool) $json['coherent'],
                'note' => '',
                'weight' => 3.0,
                'fix' => 'Die Aussagen hängen inhaltlich nicht zusammen' . ($issue !== '' ? ' — ' . $issue : '') . '.',
                'soft' => false,
                'cap' => 20,
            ],
            [
                'label' => 'Zum Seitenthema passend',
                'ok' => (bool) ($json['onTopic'] ?? false),
                'note' => '',
                'weight' => 2.0,
                'fix' => 'Der Inhalt passt nicht zum Thema dieser Seite.',
                'soft' => false,
                'cap' => 40,
            ],
            [
                'label' => 'Konkrete Aussagen',
                'ok' => (bool) ($json['substantial'] ?? false),
                'note' => '',
                'weight' => 2.0,
                'fix' => 'Zu wenig Substanz — konkrete, nachvollziehbare Aussagen statt allgemeinem Gerede.',
                'soft' => false,
                'cap' => 50,
            ],
        ];
    }

    /**
     * Plain-language verdict built from the measured checks — no LLM involved.
     *
     * @param array{score: int, violations: list<string>, hints: list<string>, checks: list<array{label: string, ok: bool, note: string, soft: bool, fix: string}>} $measured
     */
    private function explain(array $measured): string
    {
        $passed = [];
        foreach ($measured['checks'] as $check) {
            if ($check['ok']) {
                $passed[] = $check['label'] . ($check['note'] !== '' ? ' (' . $check['note'] . ')' : '');
            }
        }

        $hint = $measured['hints'] !== [] ? ' Hinweis: ' . implode(' ', $measured['hints']) : '';

        if ($measured['violations'] === []) {
            return 'Alle Kriterien erfüllt: ' . implode(' · ', $passed) . '.' . $hint;
        }

        return 'Erfüllt: ' . (($passed !== []) ? implode(' · ', $passed) : '—')
            . ' — Offen: ' . implode(' ', $measured['violations']) . $hint;
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

        $keyword = trim((string) ($ctx['focusKeyword'] ?? ''));
        $siblings = $fieldType === 'headline' ? $this->otherHeadlines($ctx, trim(strip_tags($value))) : [];

        $task = $fieldType === 'headline'
            ? ($generate
                ? 'Du schreibst eine SEO-Überschrift für einen Seitenabschnitt, basierend auf Seiteninhalt und Seitenname.'
                : 'Du optimierst eine bestehende Überschrift für SEO/AEO, ohne die Kernaussage zu ändern.')
            : ($generate
                ? 'Du schreibst einen SEO-optimierten Fließtext, basierend AUSSCHLIESSLICH auf dem gegebenen Seiteninhalt — erfinde keine Fakten.'
                : 'Du optimierst einen bestehenden Text für SEO/AEO, ohne Fakten zu ändern oder zu erfinden.');

        $system = $task . " Antworte über das JSON-Schema.\n\nDeine Fassung MUSS jede dieser Regeln erfüllen — "
            . "sie werden anschließend maschinell geprüft:\n"
            . self::RULES[$fieldType]
            . "\n\nÜBER ALLEM STEHT DER SINN: Die Fassung muss ein natürlicher, sprachlich sinnvoller Ausdruck sein. "
            . 'Eine unsinnige Wortkombination ist immer schlechter als eine erfüllte Regel. Lies deinen Vorschlag laut '
            . 'und frage dich, ob ein Mensch das so sagen würde.'
            . ($keyword !== ''
                ? "\n\nDas Fokus-Keyword der Seite lautet „" . $keyword . '“. Baue es NUR ein, wenn es thematisch '
                    . 'wirklich passt und der Satz dadurch natürlich bleibt. Passt es nicht, lässt du es weg — '
                    . 'erzwungene Keywords sind Keyword-Stuffing und schaden.'
                : '');

        // Rewriting means IMPROVING the given text — not replacing its subject.
        if (!$generate) {
            $system .= "\n\nOBERSTE REGEL: Das Thema der aktuellen Fassung bleibt erhalten. Du formulierst dieselbe "
                . 'Aussage besser — du wählst KEIN neues Thema und übernimmst nicht einfach das Hauptthema der Seite. '
                . 'Sagt die aktuelle Fassung etwas über „uns“, bleibt es eine Aussage über „uns“; geht es um '
                . 'Öffnungszeiten, bleibt es bei den Öffnungszeiten. Der Seiteninhalt dient NUR dazu, das bestehende '
                . 'Thema präziser zu fassen.';
        }

        $system .= $siblings !== []
            ? "\n\nBereits vorhandene Überschriften der Seite (nicht wiederholen):\n- " . implode("\n- ", \array_slice($siblings, 0, 12))
            : '';

        // Rewrite keeps the existing text's language; generate uses the page language.
        $system .= $generate
            ? "\n\nSprache der Ausgabe: " . $ctx['language'] . '.'
            : "\n\nSchreibe in derselben Sprache wie die aktuelle Fassung (nicht in der Sprache dieser Anweisung).";

        // When rewriting, the existing value leads and the page context is kept
        // short so it cannot drown out the actual subject.
        $baseUser = $generate
            ? ($ctx['siteName'] !== '' ? "Website: {$ctx['siteName']}\n" : '')
                . ($ctx['pageTitle'] !== '' ? "Seite/Abschnitt: {$ctx['pageTitle']}\n" : '')
                . "Seiteninhalt als Grundlage:\n" . mb_substr((string) $ctx['plaintext'], 0, 3500)
            : "DAS IST DER ZU VERBESSERNDE INHALT — sein Thema ist verbindlich:\n"
                . trim(strip_tags($value))
                . "\n\n--- Ab hier nur Hintergrund zur Einordnung, NICHT das Thema ---\n"
                . ($ctx['siteName'] !== '' ? "Website: {$ctx['siteName']}\n" : '')
                . ($ctx['pageTitle'] !== '' ? "Seite: {$ctx['pageTitle']}\n" : '')
                . "Weiterer Seiteninhalt:\n" . mb_substr((string) $ctx['plaintext'], 0, 1200);

        $purpose = ($generate ? 'optimize_generate_' : 'optimize_rewrite_') . $fieldType;
        $maxTokens = $fieldType === 'headline' ? 400 : 1400;

        $bestText = '';
        $bestScore = -1;
        $bestReason = '';
        $bestChecks = [];
        $user = $baseUser;

        // Write → measure → feed the concrete violations back → write again.
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $json = $this->askRewrite($system, $user, $maxTokens, $purpose . ($attempt > 1 ? '_retry' : ''));
            $candidate = trim((string) ($json['rewrite'] ?? ''));

            if ($candidate === '') {
                continue;
            }

            if ($fieldType === 'headline') {
                $candidate = trim(strip_tags($candidate), " \"'");
            }

            // Only the final candidate is worth a semantic call; intermediate
            // attempts are steered by the cheap deterministic violations.
            $measured = $this->scorer->score(
                $fieldType,
                $candidate,
                $keyword,
                $siblings,
                $attempt === self::MAX_ATTEMPTS ? $this->semanticChecks($fieldType, trim(strip_tags($candidate)), $ctx) : [],
            );

            if ($measured['score'] > $bestScore) {
                $bestScore = $measured['score'];
                $bestText = $candidate;
                $bestReason = $this->explain($measured);
                $bestChecks = $measured['checks'];
            }

            if ($measured['violations'] === []) {
                break; // 100/100 — done
            }

            $user = $baseUser . "\n\nDein letzter Vorschlag:\n" . $candidate
                . "\n\nDie maschinelle Prüfung beanstandet:\n- " . implode("\n- ", $measured['violations'])
                . "\nLiefere eine neue Fassung, die AUCH diese Punkte erfüllt, ohne die bereits erfüllten zu verlieren.";
        }

        if ($bestText === '') {
            throw new AiException('KI lieferte keinen Text.', AiExceptionKind::InvalidResponse);
        }

        return new PanelResult(
            score: max(0, $bestScore),
            reason: $bestReason,
            alternatives: [],
            rewrite: $bestText,
            checks: $bestChecks,
        );
    }

    /**
     * The page's other headlines — WITHOUT the one being edited, otherwise the
     * duplicate check would always fire against the field's own value.
     *
     * @param array<string, mixed> $ctx
     * @return list<string>
     */
    private function otherHeadlines(array $ctx, string $current): array
    {
        $current = mb_strtolower(trim($current));

        $out = [];
        foreach ((array) $ctx['siblingHeadlines'] as $headline) {
            $text = trim((string) $headline);
            if ($text !== '' && mb_strtolower($text) !== $current) {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * One rewrite call, JSON decoded.
     *
     * @return array<int|string, mixed>
     */
    private function askRewrite(string $system, string $user, int $maxTokens, string $purpose): array
    {
        $response = $this->ai->complete(new PromptBundle(
            systemPrompt: $system,
            userPrompt: $user,
            model: '',
            temperature: 0.5,
            maxTokens: $maxTokens,
            responseSchema: self::REWRITE_SCHEMA,
            purpose: $purpose,
        ));

        return $response->asJson() ?? [];
    }
}
