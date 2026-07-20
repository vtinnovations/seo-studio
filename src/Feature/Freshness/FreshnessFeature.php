<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Freshness;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class FreshnessFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'freshness';
    }

    public function getLabel(): string
    {
        return 'Freshness (dateModified im Schema + Sitemap lastmod)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Free;
    }
}
