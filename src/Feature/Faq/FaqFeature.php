<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Faq;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class FaqFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'faq';
    }

    public function getLabel(): string
    {
        return 'FAQ-Generierung + FAQPage-Schema (Frontend-Modul)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Pro;
    }
}
