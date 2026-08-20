<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Exchange;

use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;

/**
 * Outcome of the acceptance pipeline.
 *
 * The category is a safe internal label for diagnostics and tests. It is never
 * shown verbatim to an administrator (they get one generic message) and it must
 * never be interpreted as permission to continue — only $record !== null means
 * "authenticated and acceptable".
 */
final class AcceptanceResult
{
    private function __construct(
        public readonly ?ProvisioningRecord $record,
        public readonly string $category,
    ) {
    }

    public static function accepted(ProvisioningRecord $record): self
    {
        return new self($record, 'accepted');
    }

    public static function rejected(string $category): self
    {
        return new self(null, $category);
    }

    public function isAccepted(): bool
    {
        return $this->record !== null;
    }
}
