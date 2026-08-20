<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

/**
 * The full per-page SEO verdict: a 0–100 score, a traffic-light colour, and
 * the ordered checklist behind it.
 */
final class SeoReport
{
    /**
     * @param list<SeoCheck> $checks
     */
    public function __construct(
        public readonly int $score,
        public readonly array $checks,
        public readonly string $focusKeyword = '',
    ) {
    }

    /**
     * @param list<SeoCheck> $checks
     */
    public static function fromChecks(array $checks, string $focusKeyword = ''): self
    {
        $achieved = 0.0;
        $max = 0.0;
        foreach ($checks as $check) {
            $achieved += $check->points() * $check->weight;
            $max += $check->weight;
        }

        $score = $max > 0.0 ? (int) round($achieved / $max * 100) : 0;

        return new self(max(0, min(100, $score)), $checks, $focusKeyword);
    }

    public function color(): string
    {
        return $this->score >= 80 ? 'good' : ($this->score >= 50 ? 'mid' : 'bad');
    }

    /** Number of failing (bad) checks — the "problems to fix" count. */
    public function problemCount(): int
    {
        return \count(array_filter($this->checks, static fn (SeoCheck $c): bool => $c->status === 'bad'));
    }

    public function warningCount(): int
    {
        return \count(array_filter($this->checks, static fn (SeoCheck $c): bool => $c->status === 'warn'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'color' => $this->color(),
            'focusKeyword' => $this->focusKeyword,
            'problems' => $this->problemCount(),
            'warnings' => $this->warningCount(),
            'checks' => array_map(static fn (SeoCheck $c): array => $c->toArray(), $this->checks),
        ];
    }
}
