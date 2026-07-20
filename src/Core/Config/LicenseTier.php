<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Config;

enum LicenseTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Premium = 'premium';

    public function includes(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Pro => 1,
            self::Premium => 2,
        };
    }
}
