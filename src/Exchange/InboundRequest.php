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
 * An inbound vendor-initiated update whose HTTP request signature, metadata and
 * body have all been authenticated — or the safe category explaining the
 * refusal. The public handler never learns more than this.
 */
final class InboundRequest
{
    private function __construct(
        public readonly bool $authenticated,
        public readonly string $category,
        public readonly ?\stdClass $body,
        public readonly string $requestId,
        public readonly string $nonceDigest,
        public readonly string $bodyDigest,
        public readonly string $domain,
    ) {
    }

    public static function trusted(
        \stdClass $body,
        string $requestId,
        string $nonceDigest,
        string $bodyDigest,
        string $domain,
    ): self {
        return new self(true, 'authenticated', $body, $requestId, $nonceDigest, $bodyDigest, $domain);
    }

    public static function refused(string $category): self
    {
        return new self(false, $category, null, '', '', '', '');
    }
}
