<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class VtinnovationsSeoStudioBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
