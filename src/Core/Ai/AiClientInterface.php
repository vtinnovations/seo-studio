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
 * Contract for any LLM provider. Implementations live in Ai/Provider/.
 *
 * Contract:
 *   - Synchronous, one HTTP round-trip per complete().
 *   - MUST throw AiException (never a raw HTTP/transport exception).
 *   - MUST honour the egress kill-switch: if external calls are blocked,
 *     throw AiException(EgressBlocked) WITHOUT touching the network.
 *   - MUST read the API key from SecretStore, never from cleartext config.
 *   - MUST NOT echo response bodies or the key into logs/exceptions.
 */
interface AiClientInterface
{
    public function complete(PromptBundle $bundle): AiResponse;

    /**
     * Provider identifier as used in ConfigProvider.ai_provider
     * (e.g. 'openai', 'compatible').
     */
    public function getProviderName(): string;

    public function supportsModel(string $model): bool;

    /** A sensible default model id when the admin left the model blank. */
    public function defaultModel(): string;
}
