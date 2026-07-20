<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
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
