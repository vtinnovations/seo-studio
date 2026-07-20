<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Optimize;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class OptimizeFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'optimize';
    }

    public function getLabel(): string
    {
        return 'Text-Optimierung (Überschriften + Textblöcke: Check, Umschreiben, Generieren)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Pro;
    }
}
