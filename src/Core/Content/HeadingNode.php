<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
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
