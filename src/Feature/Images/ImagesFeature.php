<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Images;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class ImagesFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'images';
    }

    public function getLabel(): string
    {
        return 'Bild-Audit + Optimierungs-Assistent (Performance)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Premium;
    }
}
