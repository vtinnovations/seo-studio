<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\LlmsTxt;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class LlmsTxtFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'llmsTxt';
    }

    public function getLabel(): string
    {
        return 'llms.txt (maschinenlesbare Website-Übersicht für KI-Agenten)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Free;
    }
}
