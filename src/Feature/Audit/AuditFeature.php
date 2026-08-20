<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Audit;

use VTinnovations\SeoStudio\Core\Config\FeatureInterface;

final class AuditFeature implements FeatureInterface
{
    public function getId(): string
    {
        return 'audit';
    }

}
