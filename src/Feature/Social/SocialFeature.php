<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Social;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class SocialFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'social';
    }

    public function getLabel(): string
    {
        return 'Social-Media-Vorschau (Open Graph + Twitter/X Cards, Bild + Live-Vorschau)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Free;
    }
}
