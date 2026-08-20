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

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Ai\AiResponse;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Anthropic messages-API client. Endpoint: POST {base}/v1/messages.
 *
 * Shape differences vs OpenAI:
 *   - system prompt is a top-level `system` string, not a message
 *   - `max_tokens` is REQUIRED
 *   - usage fields: input_tokens / output_tokens
 *   - content is an array of typed blocks; we concatenate text blocks
 *   - structured output: no response_format — we enforce JSON via a forced
 *     tool call when a responseSchema is given, and return the tool input
 *     as the content string
 *   - vision: image blocks with base64 source inside the user message
 */
class AnthropicClient extends AbstractHttpClient
{
    protected const DEFAULT_BASE = 'https://api.anthropic.com';

    private const API_VERSION = '2023-06-01';
    private const TIMEOUT_SECS = 90;

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function defaultModel(): string
    {
        return 'claude-haiku-4-5';
    }

    public function supportsModel(string $model): bool
    {
        return str_starts_with($model, 'claude-');
    }

    public function complete(PromptBundle $bundle): AiResponse
    {
        $this->assertEgressAllowed();
        $apiKey = $this->requireApiKey();
        $endpoint = $this->resolveBaseUrl(static::DEFAULT_BASE) . '/v1/messages';

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'timeout' => self::TIMEOUT_SECS,
                'max_redirects' => 0,
                'headers' => [
                    'x-api-key' => $apiKey,
                    'anthropic-version' => self::API_VERSION,
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildPayload($bundle),
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportException $e) {
            throw new AiException(
                'Netzwerkfehler zur Anthropic-API: ' . $e->getMessage(),
                AiExceptionKind::Transport,
                previous: $e,
            );
        }

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        if ($statusCode !== 200) {
            $this->throwForErrorStatus($statusCode, $body, $response);
        }

        return $this->parseSuccess($body, $bundle, $durationMs);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(PromptBundle $bundle): array
    {
        // Vision: prepend image blocks to the user turn when images are given.
        if ($bundle->images !== []) {
            $userContent = [];
            foreach ($bundle->images as $image) {
                $userContent[] = [
                    'type' => 'image',
                    'source' => [
                        'type' => 'base64',
                        'media_type' => $image['mime'],
                        'data' => $image['data'],
                    ],
                ];
            }
            $userContent[] = ['type' => 'text', 'text' => $bundle->userPrompt];
        } else {
            $userContent = $bundle->userPrompt;
        }

        $payload = [
            'model' => $bundle->model,
            'system' => $bundle->systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => $bundle->maxTokens,
            'temperature' => $bundle->temperature,
        ];

        // Structured output via forced tool use: the model MUST call the
        // "emit_result" tool whose input schema is our response schema. The
        // tool input then IS the JSON result.
        if ($bundle->responseSchema !== null) {
            $schema = $bundle->responseSchema['schema'] ?? $bundle->responseSchema;

            $payload['tools'] = [[
                'name' => 'emit_result',
                'description' => 'Emit the structured result.',
                'input_schema' => $schema,
            ]];
            $payload['tool_choice'] = ['type' => 'tool', 'name' => 'emit_result'];
        }

        return $payload;
    }

    private function parseSuccess(string $body, PromptBundle $bundle, int $durationMs): AiResponse
    {
        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            throw new AiException(Translations::text('error.providerNonJson', 'Anthropic'), AiExceptionKind::InvalidResponse);
        }

        $content = '';
        if (\is_array($decoded['content'] ?? null)) {
            foreach ($decoded['content'] as $block) {
                if (!\is_array($block)) {
                    continue;
                }

                if (($block['type'] ?? null) === 'text') {
                    $content .= (string) ($block['text'] ?? '');
                }

                // Forced tool call: the tool input is the structured result.
                if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? '') === 'emit_result') {
                    $encoded = json_encode($block['input'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    if (\is_string($encoded)) {
                        $content = $encoded;
                        break;
                    }
                }
            }
        }

        if ($content === '') {
            $stopReason = (string) ($decoded['stop_reason'] ?? 'unknown');
            $kind = $stopReason === 'refusal'
                ? AiExceptionKind::PromptFiltered
                : AiExceptionKind::InvalidResponse;

            throw new AiException('Anthropic lieferte keinen Inhalt (stop_reason: ' . $stopReason . ').', $kind);
        }

        return new AiResponse(
            content: $content,
            tokensIn: (int) ($decoded['usage']['input_tokens'] ?? 0),
            tokensOut: (int) ($decoded['usage']['output_tokens'] ?? 0),
            durationMs: $durationMs,
            provider: $this->getProviderName(),
            model: (string) ($decoded['model'] ?? $bundle->model),
            rawResponse: $body,
        );
    }

    private function throwForErrorStatus(int $statusCode, string $body, ResponseInterface $response): never
    {
        $decoded = json_decode($body, true);
        $errorType = \is_array($decoded) ? (string) ($decoded['error']['type'] ?? '') : '';
        $errorMsg = \is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';

        $retryAfter = $this->readRetryAfter($response);

        $kind = match ($errorType) {
            'authentication_error', 'permission_error' => AiExceptionKind::Auth,
            'rate_limit_error' => AiExceptionKind::RateLimit,
            'overloaded_error', 'api_error' => AiExceptionKind::ServerError,
            'invalid_request_error' => AiExceptionKind::BadRequest,
            default => match (true) {
                $statusCode === 401 || $statusCode === 403 => AiExceptionKind::Auth,
                $statusCode === 429 => AiExceptionKind::RateLimit,
                $statusCode >= 400 && $statusCode < 500 => AiExceptionKind::BadRequest,
                $statusCode >= 500 => AiExceptionKind::ServerError,
                default => AiExceptionKind::Unknown,
            },
        };

        throw new AiException(
            sprintf('Anthropic %d (%s)%s', $statusCode, $errorType !== '' ? $errorType : 'unknown', $errorMsg !== '' ? ': ' . $errorMsg : ''),
            $kind,
            retryAfterSeconds: $retryAfter,
            providerErrorCode: $errorType !== '' ? $errorType : null,
        );
    }

    protected function readRetryAfter(ResponseInterface $response): ?int
    {
        try {
            $headers = $response->getHeaders(false);
            if (isset($headers['retry-after'][0])) {
                return (int) $headers['retry-after'][0];
            }
        } catch (HttpExceptionInterface) {
            // Headers unavailable — not fatal.
        }

        return null;
    }
}
