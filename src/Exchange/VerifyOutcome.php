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

/**
 * A structurally valid vendor answer to an activation/refresh request, or the
 * safe category explaining why there is none.
 *
 * Holding the payload here is not the same as trusting it: the signed content
 * is still unverified at this point and only PackageAcceptance may decide.
 */
final class VerifyOutcome
{
    private function __construct(
        public readonly ?string $payloadB64,
        public readonly ?\stdClass $envelope,
        public readonly string $category,
        public readonly int $httpStatus = 0,
        public readonly ?string $requestId = null,
    ) {
    }

    public static function received(string $payloadB64, \stdClass $envelope, int $httpStatus, string $requestId): self
    {
        return new self($payloadB64, $envelope, 'received', $httpStatus, $requestId);
    }

    public static function failed(string $category, int $httpStatus = 0, ?string $requestId = null): self
    {
        return new self(null, null, $category, $httpStatus, $requestId);
    }

    public function hasPackage(): bool
    {
        return $this->payloadB64 !== null && $this->envelope !== null;
    }
}
