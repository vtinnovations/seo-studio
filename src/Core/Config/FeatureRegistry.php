<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
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
