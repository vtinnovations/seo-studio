<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Meta;

final class MetaProposal
{
    public function __construct(
        public readonly string $pageTitle,
        public readonly string $description,
    ) {
    }
}
