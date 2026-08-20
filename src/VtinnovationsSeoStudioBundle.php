<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
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
