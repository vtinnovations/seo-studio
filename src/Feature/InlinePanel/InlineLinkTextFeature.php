<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class InlineLinkTextFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'inlineLinkText';
    }

    public function getLabel(): string
    {
        return 'Inline-Check: Linktexte';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Pro;
    }
}