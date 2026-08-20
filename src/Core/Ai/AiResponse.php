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
 * Normalized result of one completion. Providers adapt their native shape into
 * this so the rest of the bundle never branches on provider.
 *
 * `rawResponse` is the provider's JSON body, kept ONLY for narrow debugging.
 * It must never be echoed to logs/UI wholesale (the project guidelines §3.2).
 */
final class AiResponse
{
    public function __construct(
        public readonly string $content,
        public readonly int $tokensIn,
        public readonly int $tokensOut,
        public readonly int $durationMs,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $rawResponse,
    ) {
    }

    /**
     * Decode `content` as JSON, stripping Markdown code fences first. Returns
     * null on failure rather than throwing.
     *
     * @return array<int|string, mixed>|null
     */
    public function asJson(): ?array
    {
        $text = trim($this->content);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*\n?/', '', $text) ?? $text;
            $text = preg_replace('/\n?```\s*$/', '', $text) ?? $text;
        }

        $decoded = json_decode($text, true);

        return \is_array($decoded) ? $decoded : null;
    }

    public function totalTokens(): int
    {
        return $this->tokensIn + $this->tokensOut;
    }
}
