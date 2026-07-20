<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Schema;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class SchemaFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'schema';
    }

    public function getLabel(): string
    {
        return 'Strukturierte Daten (JSON-LD: Organization, Breadcrumb, Article)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Free;
    }
}
