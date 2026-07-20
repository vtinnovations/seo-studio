<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;
use VTinnovations\SeoStudio\Core\Config\LicenseTier;

final class PageScoreFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'pageScore';
    }

    public function getLabel(): string
    {
        return 'SEO-Bewertung pro Seite (Fokus-Keyword, Checkliste, Ampel in der Seitenliste)';
    }

    public function getRequiredTier(): LicenseTier
    {
        return LicenseTier::Free;
    }
}
