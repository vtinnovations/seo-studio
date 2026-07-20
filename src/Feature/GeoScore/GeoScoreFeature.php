<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\GeoScore;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class GeoScoreFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'geoScore';
    }

    public function getLabel(): string
    {
        return 'GEO-Score Dashboard (KI-Sichtbarkeits-Reifegrad pro Seite)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Premium;
    }
}
