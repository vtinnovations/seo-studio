<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Content;

final class HeadingNode
{
    public function __construct(
        public readonly int $level,
        public readonly string $text,
        public readonly int $contentElementId,
    ) {
    }
}
