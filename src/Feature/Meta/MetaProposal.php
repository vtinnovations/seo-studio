<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
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
