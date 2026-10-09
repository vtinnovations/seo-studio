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

use VTinnovations\SeoStudio\Core\Config\Translations;
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

        $this->add($checks, Translations::text('fieldScore.headline.notEmpty.label'), $plain !== '', 2.0, Translations::text('fieldScore.headline.notEmpty.fix'), Translations::text('fieldScore.charCount', $len));

        $this->add(
            $checks,
            Translations::text('fieldScore.headline.length.label'),
            $len >= 30 && $len <= 65,
            2.0,
            $len < 30
                ? Translations::text('fieldScore.headline.length.tooShort', $len)
                : Translations::text('fieldScore.headline.length.tooLong', $len),
            Translations::text('fieldScore.charCount', $len),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.headline.concrete.label'),
            !$this->hasFiller($lower) && \count($words) >= 3,
            2.0,
            Translations::text('fieldScore.headline.concrete.fix'),
        );

        $this->add(
            $checks,
            Translations::text('check.activeVoice.label'),
            preg_match(self::PASSIVE, $plain) !== 1,
            1.0,
            Translations::text('fieldScore.headline.activeVoice.fix'),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.headline.duplicate.label'),
            !$this->duplicates($lower, $siblings),
            1.0,
            Translations::text('fieldScore.headline.duplicate.fix'),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.headline.format.label'),
            preg_match('/["“”*_#|]|<[a-z]/i', $plain) !== 1,
            1.0,
            Translations::text('fieldScore.headline.format.fix'),
        );

        // AEO is satisfiable EITHER WAY — a question or a statement that names
        // a concrete subject. No contradiction with the length rule.
        $isQuestion = str_ends_with($plain, '?');
        $this->add(
            $checks,
            Translations::text('fieldScore.headline.answersIntent.label'),
            $isQuestion || \count($words) >= 4,
            1.5,
            Translations::text('fieldScore.headline.answersIntent.fix'),
            $isQuestion ? Translations::text('fieldScore.questionForm') : Translations::text('fieldScore.statementForm'),
        );

        // SOFT: a focus keyword belongs in the page title and H1 — forcing it
        // into every section headline produces keyword stuffing and nonsense
        // ("Öffnungszeiten für Contao-Freelancer"). Never costs points.
        if ($keyword !== '') {
            $this->add(
                $checks,
                Translations::text('fieldScore.keywordPresent.label'),
                $this->containsKeyword($lower, $keyword),
                0.0,
                Translations::text('fieldScore.keywordMissing.fix', $keyword),
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
            Translations::text('fieldScore.text.minLength.label'),
            $wordCount >= self::TEXT_MIN_WORDS,
            2.0,
            Translations::text('fieldScore.text.minLength.fix', $wordCount, self::TEXT_MIN_WORDS),
            Translations::text('fieldScore.wordCount', $wordCount),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.text.substantial.label'),
            $wordCount >= self::TEXT_GOOD_WORDS,
            1.5,
            Translations::text('fieldScore.text.substantial.fix', $wordCount, self::TEXT_GOOD_WORDS),
        );

        // Structure only becomes a meaningful signal once there is real text.
        if ($wordCount >= self::TEXT_GOOD_WORDS) {
            $this->add(
                $checks,
                Translations::text('fieldScore.text.paragraphs.label'),
                $paragraphs >= 2,
                1.0,
                Translations::text('fieldScore.text.paragraphs.fix'),
                Translations::text('fieldScore.paragraphCount', $paragraphs),
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
            Translations::text('fieldScore.text.noRepeats.label'),
            $repeated === 0,
            2.0,
            Translations::text('fieldScore.text.noRepeats.fix', $repeated),
            $repeated > 0 ? Translations::text('fieldScore.repeatCount', $repeated) : '',
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
            Translations::text('fieldScore.text.variety.label'),
            $variety >= self::MIN_VARIETY,
            1.5,
            Translations::text('fieldScore.text.variety.fix', $variety * 100),
            Translations::text('fieldScore.varietyPercent', $variety * 100),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.text.answerFirst.label'),
            $firstWords > 0 && $firstWords <= 25,
            2.0,
            Translations::text('fieldScore.text.answerFirst.fix'),
            Translations::text('fieldScore.firstSentenceWords', $firstWords),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.text.shortSentences.label'),
            $avgWords <= 18,
            1.5,
            Translations::text('fieldScore.text.shortSentences.fix', $avgWords),
            Translations::text('fieldScore.avgWordsPerSentence', $avgWords),
        );

        $passive = preg_match_all(self::PASSIVE, $plain);
        $this->add(
            $checks,
            Translations::text('check.activeVoice.label'),
            $passive / $sentenceCount <= 0.25,
            1.0,
            Translations::text('fieldScore.text.tooPassive.fix'),
        );

        $this->add($checks, Translations::text('fieldScore.text.noFiller.label'), !$this->hasFiller($lower), 1.0, Translations::text('fieldScore.text.noFiller.fix'));

        // Same two readability signals the page checklist uses, with the same
        // three grades and the same thresholds — otherwise an element scores 100
        // while its page is flagged "hard to read".
        $flesch = GermanText::fleschAmstad($plain, $wordCount, $sentenceCount);
        $fleschShown = (int) round($flesch);
        $this->addGraded(
            $checks,
            Translations::text('fieldScore.text.readability.label'),
            GermanText::grade($flesch, GermanText::FLESCH_GOOD, GermanText::FLESCH_OK),
            1.0,
            Translations::text('fieldScore.text.readability.fix', $fleschShown),
            Translations::text('fieldScore.readabilityScore', $fleschShown),
        );

        $transitions = GermanText::transitionRatio($sentences) * 100;
        $transitionsShown = (int) round($transitions);
        $this->addGraded(
            $checks,
            Translations::text('fieldScore.text.transitions.label'),
            GermanText::grade($transitions, GermanText::TRANSITION_GOOD, GermanText::TRANSITION_OK),
            1.0,
            Translations::text('fieldScore.text.transitions.fix', $transitionsShown),
            Translations::text('fieldScore.transitionsPercent', $transitionsShown),
        );

        $this->add(
            $checks,
            Translations::text('fieldScore.text.allowedHtml.label'),
            preg_match('/<(?!\/?(p|strong|ul|ol|li|em|br)\b)[a-z]/i', $value) !== 1,
            1.0,
            Translations::text('fieldScore.text.allowedHtml.fix'),
        );

        // SOFT — see scoreHeadline(): never force a keyword into a text.
        if ($keyword !== '') {
            $this->add(
                $checks,
                Translations::text('fieldScore.keywordPresent.label'),
                $this->containsKeyword($lower, $keyword),
                0.0,
                Translations::text('fieldScore.keywordMissing.fix', $keyword),
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
