<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Config;

final class FeatureRegistry
{
    /** @var array<string, FeatureInterface> */
    private array $features = [];

    /**
     * @param iterable<FeatureInterface> $features
     */
    public function __construct(iterable $features)
    {
        foreach ($features as $feature) {
            $this->features[$feature->getId()] = $feature;
        }
    }

    /**
     * @return array<string, FeatureInterface>
     */
    public function all(): array
    {
        return $this->features;
    }

    public function get(string $id): ?FeatureInterface
    {
        return $this->features[$id] ?? null;
    }
}
