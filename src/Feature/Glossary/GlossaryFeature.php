<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Glossary;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class GlossaryFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'glossary';
    }

    public function getLabel(): string
    {
        return 'KI-Glossar (Begriffe + Definitionen, Frontend-Modul, Schema)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Pro;
    }
}
