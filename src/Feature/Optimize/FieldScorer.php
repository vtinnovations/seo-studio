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

use VTinnovations\SeoStudio\Core\Content\GermanText;

/**
 * DETERMINISTIC scoring for headline and text fields.
 *
 * The LLM writes, this class measures — never the other way round. That makes
 * the score reproducible, explainable and, above all, *reachable*: every
 * criterion is objectively checkable and none of them contradict each other,
 * so a genuinely good headline really does score 100.
 *
 * Each violation carries a plain-language instruction, which is fed straight
 * back into the rewrite prompt when a proposal misses the mark.
 */
final class FieldScorer
{
    /** Generic phrases that say nothing — the classic SEO dead weight. */
    private const FILLER = [
        'unsere leistungen', 'unsere produkte', 'unsere angebote', 'über uns', 'willkommen',
        'herzlich willkommen', 'startseite', 'home', 'leistungen', 'services', 'aktuelles',
        'in der heutigen zeit', 'in der heutigen welt', 'wir freuen uns',
    ];

    private const PASSIVE = '/\b(wird|werden|wurde|wurden|geworden)\b/iu';

    /** Below this, a text is a stub — the other criteria say nothing. */
    private const TEXT_MIN_WORDS = 40;

    /** Guideline for body copy with real substance. */
    private const TEXT_GOOD_WORDS = 120;

    /**
     * Minimum share of distinct words (type-token ratio) in the first 150
     * words. Normal German prose sits well above 0.5; copy-pasted padding
     * collapses towards 0.2.
     */
    private const MIN_VARIETY = 0.35;

    /**
     * @param list<string> $siblings other headlines on the same page
     * @param list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap?: int|null, grade?: string|null}> $extraChecks
     *        semantic judgements contributed by the LLM (coherence, topic, substance) —
     *        PHP measures form, only a language model can judge meaning
     * @return array{score: int, violations: list<string>, hints: list<string>, checks: list<array{label: string, ok: bool, note: string, soft: bool, fix: string}>}
     */
    public function score(string $fieldType, string $value, string $keyword = '', array $siblings = [], array $extraChecks = []): array
    {
        return $fieldType === 'headline'
            ? $this->scoreHeadline($value, $keyword, $siblings, $extraChecks)
            : $this->scoreText($value, $keyword, $extraChecks);
    }

    /**
     * @param list<string> $siblings
     * @param list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap?: int|null, grade?: string|null}> $extraChecks
     * @return array{score: int, violations: list<string>, hints: list<string>, checks: list<array{label: string, ok: bool, note: string, soft: bool, fix: string}>}
     */
    private function scoreHeadline(string $value, string $keyword, array $siblings, array $extraChecks = []): array
    {
        $plain = trim(strip_tags($value));
        $len = mb_strlen($plain);
        $lower = mb_strtolower($plain);
        $words = $this->words($plain);

        $checks = [];

        $this->add($checks, 'Nicht leer', $plain !== '', 2.0, 'Überschrift darf nicht leer sein.', $len . ' Zeichen');

        $this->add(
            $checks,
            'Länge 30–65 Zeichen',
            $len >= 30 && $len <= 65,
            2.0,
            $len < 30
                ? 'Zu kurz (' . $len . ' Zeichen) — auf 30–65 Zeichen ausbauen und konkreter werden.'
                : 'Zu lang (' . $len . ' Zeichen) — auf höchstens 65 Zeichen kürzen.',
            $len . ' Zeichen',
        );

        $this->add(
            $checks,
            'Konkret statt Floskel',
            !$this->hasFiller($lower) && \count($words) >= 3,
            2.0,
            'Zu allgemein — benenne das konkrete Thema statt einer Floskel wie „Unsere Leistungen“.',
        );

        $this->add(
            $checks,
            'Aktive Sprache',
            preg_match(self::PASSIVE, $plain) !== 1,
            1.0,
            'Passiv vermeiden — aktiv formulieren.',
        );

        $this->add(
            $checks,
            'Keine Dopplung',
            !$this->duplicates($lower, $siblings),
            1.0,
            'Eine andere Überschrift der Seite lautet fast gleich — klar abgrenzen.',
        );

        $this->add(
            $checks,
            'Sauberes Format',
            preg_match('/["“”*_#|]|<[a-z]/i', $plain) !== 1,
            1.0,
            'Keine Anführungszeichen, kein Markdown, kein HTML.',
        );

        // AEO is satisfiable EITHER WAY — a question or a statement that names
        // a concrete subject. No contradiction with the length rule.
        $isQuestion = str_ends_with($plain, '?');
        $this->add(
            $checks,
            'Beantwortet ein Nutzeranliegen',
            $isQuestion || \count($words) >= 4,
            1.5,
            'Zu vage — entweder als Frage formulieren oder das Thema mit mindestens vier bedeutungstragenden Wörtern benennen.',
            $isQuestion ? 'Frageform' : 'Aussageform',
        );

        // SOFT: a focus keyword belongs in the page title and H1 — forcing it
        // into every section headline produces keyword stuffing and nonsense
        // ("Öffnungszeiten für Contao-Freelancer"). Never costs points.
        if ($keyword !== '') {
            $this->add(
                $checks,
                'Fokus-Keyword enthalten',
                $this->containsKeyword($lower, $keyword),
                0.0,
                'Das Fokus-Keyword „' . $keyword . '“ kommt nicht vor — nur einbauen, wenn es thematisch wirklich passt.',
                '',
                true,
            );
        }

        return $this->summarise(array_merge($checks, $extraChecks));
    }

    /**
     * @param list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap?: int|null, grade?: string|null}> $extraChecks
     * @return array{score: int, violations: list<string>, hints: list<string>, checks: list<array{label: string, ok: bool, note: string, soft: bool, fix: string}>}
     */
    private function scoreText(string $value, string $keyword, array $extraChecks = []): array
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        $lower = mb_strtolower($plain);
        $words = $this->words($plain);
        $wordCount = \count($words);

        $sentences = array_values(array_filter(
            preg_split('/(?<=[.!?])\s+/u', $plain) ?: [],
            static fn (string $s): bool => trim($s) !== '',
        ));
        $sentenceCount = max(1, \count($sentences));
        $avgWords = $wordCount / $sentenceCount;
        $firstWords = \count($this->words($sentences[0] ?? ''));

        $paragraphs = max(1, (int) preg_match_all('/<p\b/i', $value));

        $checks = [];

        $this->add(
            $checks,
            'Mindestlänge (40 Wörter)',
            $wordCount >= self::TEXT_MIN_WORDS,
            2.0,
            'Zu wenig Text (' . $wordCount . ' Wörter) — auf mindestens ' . self::TEXT_MIN_WORDS . ' Wörter ausbauen.',
            $wordCount . ' Wörter',
        );

        $this->add(
            $checks,
            'Substanzieller Umfang (120+ Wörter)',
            $wordCount >= self::TEXT_GOOD_WORDS,
            1.5,
            'Nur ' . $wordCount . ' Wörter — Suchmaschinen bevorzugen Substanz, Richtwert ' . self::TEXT_GOOD_WORDS . '+ Wörter.',
        );

        // Structure only becomes a meaningful signal once there is real text.
        if ($wordCount >= self::TEXT_GOOD_WORDS) {
            $this->add(
                $checks,
                'Gegliederte Absätze',
                $paragraphs >= 2,
                1.0,
                'Langer Text in einem Block — in mehrere Absätze gliedern.',
                $paragraphs . ' Absätze',
            );
        }

        // Padding guard #1: the same sentence pasted repeatedly is not content.
        $normalised = array_values(array_filter(
            array_map(fn (string $s): string => $this->normalise($s), $sentences),
            static fn (string $s): bool => $s !== '',
        ));
        $repeated = \count($normalised) - \count(array_unique($normalised));

        $this->add(
            $checks,
            'Keine Satzwiederholungen',
            $repeated === 0,
            2.0,
            $repeated . ' Satz/Sätze wiederholen sich wörtlich — der Text muss echten Inhalt liefern, nicht denselben Satz vervielfachen.',
            $repeated > 0 ? $repeated . ' Wiederholung(en)' : '',
        );

        // Padding guard #2: lexical variety over the first 150 words.
        $sample = \array_slice(
            array_map(static fn (string $w): string => mb_strtolower(trim($w, ".,;:!?–-\"'“”")), $words),
            0,
            150,
        );
        $variety = $sample !== [] ? \count(array_unique($sample)) / \count($sample) : 1.0;

        $this->add(
            $checks,
            'Sprachliche Vielfalt',
            $variety >= self::MIN_VARIETY,
            1.5,
            sprintf('Sehr viele Wortwiederholungen (nur %.0f %% verschiedene Wörter) — abwechslungsreich und inhaltlich formulieren.', $variety * 100),
            sprintf('%.0f %% verschiedene Wörter', $variety * 100),
        );

        $this->add(
            $checks,
            'Antwort-zuerst',
            $firstWords > 0 && $firstWords <= 25,
            2.0,
            'Der erste Satz muss das Thema direkt beantworten und höchstens 25 Wörter haben.',
            $firstWords . ' Wörter im 1. Satz',
        );

        $this->add(
            $checks,
            'Kurze Sätze',
            $avgWords <= 18,
            1.5,
            sprintf('Sätze zu lang (Ø %.0f Wörter) — auf höchstens 18 Wörter je Satz kürzen.', $avgWords),
            sprintf('Ø %.0f Wörter/Satz', $avgWords),
        );

        $passive = preg_match_all(self::PASSIVE, $plain);
        $this->add(
            $checks,
            'Aktive Sprache',
            $passive / $sentenceCount <= 0.25,
            1.0,
            'Zu viel Passiv — aktiv umformulieren.',
        );

        $this->add($checks, 'Keine Floskeln', !$this->hasFiller($lower), 1.0, 'Floskeln streichen („In der heutigen Zeit“, „Willkommen“).');

        // Same two readability signals the page checklist uses, with the same
        // three grades and the same thresholds — otherwise an element scores 100
        // while its page is flagged "hard to read".
        $flesch = GermanText::fleschAmstad($plain, $wordCount, $sentenceCount);
        $fleschShown = (int) round($flesch);
        $this->addGraded(
            $checks,
            'Verständlichkeit',
            GermanText::grade($flesch, GermanText::FLESCH_GOOD, GermanText::FLESCH_OK),
            1.0,
            sprintf('Lesbarkeit %d/100 — kürzere Wörter und Sätze verwenden.', $fleschShown),
            sprintf('Lesbarkeit %d/100', $fleschShown),
        );

        $transitions = GermanText::transitionRatio($sentences) * 100;
        $transitionsShown = (int) round($transitions);
        $this->addGraded(
            $checks,
            'Übergangswörter',
            GermanText::grade($transitions, GermanText::TRANSITION_GOOD, GermanText::TRANSITION_OK),
            1.0,
            sprintf('Nur %d %% der Sätze mit Verbindungswörtern — „außerdem“, „daher“, „zunächst“ führen den Leser.', $transitionsShown),
            sprintf('%d %% der Sätze', $transitionsShown),
        );

        $this->add(
            $checks,
            'Erlaubtes HTML',
            preg_match('/<(?!\/?(p|strong|ul|ol|li|em|br)\b)[a-z]/i', $value) !== 1,
            1.0,
            'Nur <p>, <strong>, <em>, <ul>, <ol>, <li> verwenden.',
        );

        // SOFT — see scoreHeadline(): never force a keyword into a text.
        if ($keyword !== '') {
            $this->add(
                $checks,
                'Fokus-Keyword enthalten',
                $this->containsKeyword($lower, $keyword),
                0.0,
                'Das Fokus-Keyword „' . $keyword . '“ kommt nicht vor — nur einbauen, wenn es thematisch wirklich passt.',
                '',
                true,
            );
        }

        $result = $this->summarise(array_merge($checks, $extraChecks));

        // LENGTH IS A GATE, not just one point among many: a stub trivially
        // satisfies "short sentences", "answer-first" and "no filler" *because*
        // it is a stub. Without enough text the other checks prove nothing, so
        // the score stays proportional to how much text actually exists.
        if ($wordCount < self::TEXT_MIN_WORDS) {
            $capped = (int) round($wordCount / self::TEXT_MIN_WORDS * self::TEXT_MIN_WORDS);
            $result['score'] = min($result['score'], $capped);
        }

        return $result;
    }

    /**
     * @param list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap?: int|null, grade?: string|null}> $checks
     */
    private function add(array &$checks, string $label, bool $ok, float $weight, string $fix, string $note = '', bool $soft = false, ?int $cap = null): void
    {
        $checks[] = ['label' => $label, 'ok' => $ok, 'note' => $note, 'weight' => $weight, 'fix' => $fix, 'soft' => $soft, 'cap' => $cap, 'grade' => null];
    }

    /**
     * A three-grade criterion: good / warn / bad, exactly as the page checklist
     * grades the same value. "warn" earns half the weight and only a hint — the
     * text is acceptable, not ideal — so the element verdict can never call a
     * value green that the page marks yellow.
     *
     * @param list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap: int|null, grade: string|null}> $checks
     * @param 'good'|'warn'|'bad' $grade
     */
    private function addGraded(array &$checks, string $label, string $grade, float $weight, string $fix, string $note = ''): void
    {
        $checks[] = [
            'label' => $label,
            'ok' => $grade === 'good',
            'note' => $note,
            'weight' => $weight,
            'fix' => $fix,
            // The panel renders "soft && !ok" as the yellow warning state.
            'soft' => $grade === 'warn',
            'cap' => null,
            'grade' => $grade,
        ];
    }

    /**
     * Hard criteria drive the score and the rewrite loop; soft ones are advice
     * only — they never cost points and never trigger a retry, so nothing gets
     * forced into the text at the expense of meaning.
     *
     * @param list<array{label: string, ok: bool, note: string, weight: float, fix: string, soft: bool, cap?: int|null, grade?: string|null}> $checks
     * @return array{score: int, violations: list<string>, hints: list<string>, checks: list<array{label: string, ok: bool, note: string, soft: bool, fix: string}>}
     */
    private function summarise(array $checks): array
    {
        $achieved = 0.0;
        $max = 0.0;
        $violations = [];
        $hints = [];
        $public = [];
        $cap = null;

        foreach ($checks as $check) {
            if (($check['grade'] ?? null) === 'warn') {
                // Acceptable but not ideal: half the points, a hint, no retry.
                $max += $check['weight'];
                $achieved += $check['weight'] / 2;
                $hints[] = $check['fix'];
            } elseif (!$check['soft']) {
                $max += $check['weight'];
                if ($check['ok']) {
                    $achieved += $check['weight'];
                } else {
                    $violations[] = $check['fix'];

                    // A failed knock-out criterion caps the whole score: a text
                    // without a common thread is unusable, however clean its form.
                    if (isset($check['cap'])) {
                        $cap = $cap === null ? $check['cap'] : min($cap, $check['cap']);
                    }
                }
            } elseif (!$check['ok']) {
                $hints[] = $check['fix'];
            }

            $public[] = [
                'label' => $check['label'],
                'ok' => $check['ok'],
                'note' => $check['note'],
                'soft' => $check['soft'],
                // What it takes to tick this box — shown in the panel checklist.
                'fix' => $check['ok'] ? '' : $check['fix'],
            ];
        }

        $score = $max > 0.0 ? (int) round($achieved / $max * 100) : 0;

        return [
            'score' => $cap !== null ? min($score, $cap) : $score,
            'violations' => $violations,
            'hints' => $hints,
            'checks' => $public,
        ];
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        return array_values(array_filter(
            preg_split('/\s+/u', trim($text)) ?: [],
            static fn (string $w): bool => mb_strlen(trim($w, ".,;:!?–-")) > 2,
        ));
    }

    private function hasFiller(string $lower): bool
    {
        foreach (self::FILLER as $phrase) {
            if ($lower === $phrase || str_starts_with($lower, $phrase . ' ') || str_contains($lower, ' ' . $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $siblings
     */
    private function duplicates(string $lower, array $siblings): bool
    {
        // Normalised compare so "Unsere Leistungen." and "unsere leistungen"
        // count as the same headline.
        $needle = $this->normalise($lower);
        if ($needle === '') {
            return false;
        }

        foreach ($siblings as $sibling) {
            if ($this->normalise($sibling) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function normalise(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/u', ' ', mb_strtolower($value)));
    }

    /**
     * Keyword match: the whole phrase, or — for comma-separated input — every
     * single term. Case- and whitespace-tolerant.
     */
    private function containsKeyword(string $lower, string $keyword): bool
    {
        $keyword = mb_strtolower(trim($keyword));
        if ($keyword === '') {
            return true;
        }

        if (str_contains($lower, $keyword)) {
            return true;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $keyword)), static fn (string $p): bool => $p !== ''));
        if (\count($parts) < 2) {
            return false;
        }

        foreach ($parts as $part) {
            if (!str_contains($lower, $part)) {
                return false;
            }
        }

        return true;
    }
}
