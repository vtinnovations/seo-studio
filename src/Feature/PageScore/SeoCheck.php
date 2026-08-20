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
 * One line item in the per-page SEO checklist (Yoast/Rank-Math style).
 *
 * status: good | warn | bad. fix: optional machine hint that the UI turns
 * into a one-click action (e.g. "meta", "images", "optimize").
 */
final class SeoCheck
{
    public function __construct(
        public readonly string $group,
        public readonly string $status,
        public readonly string $label,
        public readonly string $hint = '',
        public readonly float $weight = 1.0,
        public readonly string $fix = '',
    ) {
    }

    public function points(): float
    {
        return match ($this->status) {
            'good' => 1.0,
            'warn' => 0.5,
            default => 0.0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'status' => $this->status,
            'label' => $this->label,
            'hint' => $this->hint,
            'fix' => $this->fix,
        ];
    }
}
