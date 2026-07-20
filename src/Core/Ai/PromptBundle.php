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
 * Immutable input for one completion. Provider-agnostic; each client adapts it
 * to its native request shape (e.g. the OpenAI chat/completions format).
 */
final class PromptBundle
{
    /**
     * @param array<string, mixed>|null               $responseSchema JSON schema for structured output (optional).
     * @param list<array{mime: string, data: string}> $images         Inline images: raw base64 (no data: prefix) + mime. Empty for text-only calls.
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly string $userPrompt,
        public readonly string $model,
        public readonly float $temperature = 0.4,
        public readonly int $maxTokens = 1024,
        public readonly ?array $responseSchema = null,
        public readonly string $purpose = '',
        public readonly array $images = [],
    ) {
    }

    public function withModel(string $model): self
    {
        return new self(
            $this->systemPrompt,
            $this->userPrompt,
            $model,
            $this->temperature,
            $this->maxTokens,
            $this->responseSchema,
            $this->purpose,
            $this->images,
        );
    }
}
