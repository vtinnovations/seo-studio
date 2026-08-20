<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\GeoScore;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;

final class GeoScoreFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'geoScore';
    }

}
