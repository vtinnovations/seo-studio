<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Ai\Provider;

/**
 * OpenAI-compatible client for self-hosted / third-party endpoints (Ollama,
 * LM Studio, OpenRouter, …). Same wire format as OpenAI; the admin supplies the
 * base URL. No baked-in default endpoint — base URL is required for this
 * provider, validated by AbstractHttpClient::resolveBaseUrl().
 */
final class CompatibleClient extends OpenAiClient
{
    public function getProviderName(): string
    {
        return 'compatible';
    }

    public function defaultModel(): string
    {
        return 'gpt-3.5-turbo';
    }

    public function supportsModel(string $model): bool
    {
        // Compatible endpoints expose arbitrary model ids; don't gatekeep.
        return $model !== '';
    }
}
