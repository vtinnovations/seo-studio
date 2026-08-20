<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Content;

/**
 * Shared readability primitives.
 *
 * Both the page checklist and the per-field check must judge readability the
 * same way — otherwise an element can score 98 while the page it sits on is
 * marked "hard to read" for criteria the element check never looked at. One
 * implementation, used by both.
 */
final class GermanText
{
    /**
     * German transition words (Übergangswörter) — a Yoast readability signal.
     * Text that connects its sentences reads as coherent argument, not a list.
     *
     * @var list<string>
     */
    public const TRANSITION_WORDS = [
        'außerdem', 'zudem', 'darüber hinaus', 'ferner', 'weiterhin', 'ebenso', 'auch',
        'daher', 'deshalb', 'deswegen', 'folglich', 'somit', 'infolgedessen',
        'jedoch', 'dennoch', 'trotzdem', 'allerdings', 'andererseits', 'einerseits', 'hingegen',
        'zunächst', 'zuerst', 'danach', 'anschließend', 'schließlich', 'zuletzt', 'abschließend',
        'beispielsweise', 'zum beispiel', 'insbesondere', 'vor allem', 'nämlich',
        'zusammenfassend', 'kurz gesagt', 'erstens', 'zweitens', 'drittens',
        'weil', 'obwohl', 'während', 'sodass', 'damit', 'sofern',
    ];

    /** Flesch-Amstad: from here the text reads easily. */
    public const FLESCH_GOOD = 60;

    /** Below this the text counts as hard to understand. */
    public const FLESCH_OK = 40;

    /** Share of sentences with a transition word — from here the text guides the reader. */
    public const TRANSITION_GOOD = 30;

    /** Below this the sentences read as a disconnected list. */
    public const TRANSITION_OK = 15;

    /**
     * Grade a value against a good/ok threshold pair.
     *
     * Grades the ROUNDED value, because that is the number the user sees. Judging
     * 39.6 as "bad" while printing "40/100" is the one thing a score must never do.
     *
     * @return 'good'|'warn'|'bad'
     */
    public static function grade(float $value, int $good, int $ok): string
    {
        $rounded = (int) round($value);

        if ($rounded >= $good) {
            return 'good';
        }

        return $rounded >= $ok ? 'warn' : 'bad';
    }

    /**
     * Share of sentences that use at least one transition word (0.0–1.0).
     *
     * @param list<string> $sentences
     */
    public static function transitionRatio(array $sentences): float
    {
        if ($sentences === []) {
            return 0.0;
        }

        $withTransition = 0;

        foreach ($sentences as $sentence) {
            $lower = mb_strtolower($sentence);

            foreach (self::TRANSITION_WORDS as $word) {
                if (str_contains($lower, $word)) {
                    ++$withTransition;
                    break;
                }
            }
        }

        return $withTransition / \count($sentences);
    }

    /**
     * Flesch reading ease, German adaptation (Amstad):
     * 180 − average sentence length − 58.5 × average syllables per word.
     * Clamped to 0–100.
     */
    public static function fleschAmstad(string $text, int $wordCount, int $sentenceCount): float
    {
        if ($wordCount < 1 || $sentenceCount < 1) {
            return 100.0;
        }

        $avgWords = $wordCount / $sentenceCount;
        $avgSyllables = self::syllables($text) / $wordCount;

        return max(0.0, min(100.0, 180 - $avgWords - 58.5 * $avgSyllables));
    }

    /**
     * Syllable estimate: count vowel groups. Rough but stable across German
     * and English, and good enough for a relative readability signal.
     */
    public static function syllables(string $text): int
    {
        $groups = preg_match_all('/[aeiouäöüy]+/iu', $text);

        return max(1, (int) $groups);
    }
}
