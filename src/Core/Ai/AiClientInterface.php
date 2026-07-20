<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
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
