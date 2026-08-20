<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

/**
 * One inline verdict: traffic-light score, one-sentence reason, up to three
 * apply-on-click alternatives.
 */
final class PanelResult
{
    /**
     * @param int $score 0-100
     * @param list<string> $alternatives
     * @param string $rewrite optional SEO-optimized rewrite / generated text
     * @param list<array{label: string, ok: bool, note: string, soft: bool, fix: string}> $checks
     *        the individual criteria behind the score — what it takes to reach 100
     */
    public function __construct(
        public readonly int $score,
        public readonly string $reason,
        public readonly array $alternatives = [],
        public readonly bool $fromCache = false,
        public readonly string $rewrite = '',
        public readonly array $checks = [],
    ) {
    }

    /** Traffic light: good (>=80) / mid (>=50) / bad. */
    public function color(): string
    {
        return $this->score >= 80 ? 'good' : ($this->score >= 50 ? 'mid' : 'bad');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'color' => $this->color(),
            'reason' => $this->reason,
            'alternatives' => $this->alternatives,
            'rewrite' => $this->rewrite,
            'checks' => $this->checks,
        ];
    }

    /**
     * @param array<int|string, mixed> $data
     */
    public static function fromArray(array $data, bool $fromCache = false): self
    {
        $alternatives = [];
        foreach ((array) ($data['alternatives'] ?? []) as $alt) {
            if (\is_string($alt) && trim($alt) !== '') {
                $alternatives[] = trim($alt);
            }
        }

        return new self(
            score: max(0, min(100, (int) ($data['score'] ?? 0))),
            reason: trim((string) ($data['reason'] ?? '')),
            alternatives: \array_slice($alternatives, 0, 3),
            fromCache: $fromCache,
        );
    }
}
