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

use VTinnovations\SeoStudio\Core\Ai\PromptBundle;

/**
 * Mistral AI chat-completions client. Endpoint: POST {base}/chat/completions.
 *
 * Mistral's API follows the OpenAI chat-completions shape (Bearer auth,
 * messages, max_tokens, temperature, response_format, usage.*_tokens), so this
 * only overrides what differs:
 *   - fixed endpoint https://api.mistral.ai/v1 (never the configurable base URL);
 *   - vision parts carry image_url as a plain string, not {url: …};
 *   - errors are top-level {object, message, type, code}, and message may be
 *     a structured validation object instead of a string;
 *   - reasoning models may return content as a list of typed chunks.
 */
final class MistralClient extends OpenAiClient
{
    protected const DEFAULT_BASE = 'https://api.mistral.ai/v1';

    /** Longest provider error message passed on to the UI. */
    private const MAX_ERROR_LENGTH = 300;

    private const MODEL_PREFIXES = [
        'mistral-',
        'open-mistral-',
        'ministral-',
        'magistral-',
        'pixtral-',
        'codestral-',
        'devstral-',
    ];

    public function getProviderName(): string
    {
        return 'mistral';
    }

    /**
     * Small, inexpensive, strong in German and vision-capable, so the
     * alt-text check works without picking a different model.
     */
    public function defaultModel(): string
    {
        return 'mistral-small-latest';
    }

    public function supportsModel(string $model): bool
    {
        foreach (self::MODEL_PREFIXES as $prefix) {
            if (str_starts_with($model, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function providerLabel(): string
    {
        return 'Mistral AI';
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPayload(PromptBundle $bundle): array
    {
        $payload = parent::buildPayload($bundle);

        // Mistral expects image_url as a string (URL or data URI).
        $content = $payload['messages'][1]['content'] ?? null;
        if (\is_array($content)) {
            foreach ($content as $i => $part) {
                if (\is_array($part) && ($part['type'] ?? null) === 'image_url' && \is_array($part['image_url'] ?? null)) {
                    $content[$i]['image_url'] = (string) ($part['image_url']['url'] ?? '');
                }
            }

            $payload['messages'][1]['content'] = $content;
        }

        // The parent omits temperature only for OpenAI's o1/o3 models; every
        // Mistral model accepts it, so make sure it is always present.
        $payload['temperature'] = $bundle->temperature;

        return $payload;
    }

    /**
     * @param array<mixed> $decoded
     */
    protected function extractContent(array $decoded): ?string
    {
        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if (\is_string($content)) {
            return $content !== '' ? $content : null;
        }

        // Reasoning models answer with typed chunks; keep only the text ones.
        if (\is_array($content)) {
            $text = '';
            foreach ($content as $chunk) {
                if (\is_array($chunk) && ($chunk['type'] ?? null) === 'text' && \is_string($chunk['text'] ?? null)) {
                    $text .= $chunk['text'];
                }
            }

            return $text !== '' ? $text : null;
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function extractError(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            return ['', ''];
        }

        // Some gateways in front of the API answer in OpenAI's nested shape.
        if (\is_array($decoded['error'] ?? null)) {
            return parent::extractError($body);
        }

        // A structured message (e.g. a 422 validation detail) may echo the
        // request, so only a plain-string message is passed on, and bounded.
        $message = \is_string($decoded['message'] ?? null)
            ? mb_substr(trim(strip_tags($decoded['message'])), 0, self::MAX_ERROR_LENGTH)
            : '';

        $code = $decoded['code'] ?? null;
        if (!\is_string($code) && !\is_int($code)) {
            $code = $decoded['type'] ?? '';
        }

        return [$message, \is_string($code) || \is_int($code) ? (string) $code : ''];
    }
}
