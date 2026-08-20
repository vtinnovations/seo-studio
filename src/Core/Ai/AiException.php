<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Ai;

/**
 * Thrown on any AI-call failure. `kind` lets callers pick the right reaction
 * (retry vs. surface to admin). MUST NOT carry secrets or raw provider bodies
 * in its message (the project guidelines §3.1/§3.2).
 *
 *   - EgressBlocked → the "no external calls" switch is on. Never reached the
 *     network. Not retryable; the admin must opt in first.
 */
final class AiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly AiExceptionKind $kind,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $providerErrorCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isRetryable(): bool
    {
        return match ($this->kind) {
            AiExceptionKind::RateLimit,
            AiExceptionKind::Transport,
            AiExceptionKind::ServerError => true,
            default => false,
        };
    }
}
