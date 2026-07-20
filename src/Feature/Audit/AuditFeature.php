<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class AuditFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'audit';
    }

    public function getLabel(): string
    {
        return 'Audits (robots.txt KI-Crawler, Struktur)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Free;
    }
}
