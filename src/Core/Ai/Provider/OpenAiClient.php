<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Ai\Provider;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Ai\AiResponse;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;

/**
 * OpenAI chat-completions client. Endpoint: POST {base}/chat/completions.
 *
 * Key comes from SecretStore (never cleartext config). The egress switch is
 * checked before any network work. The configurable base URL also powers the
 * "compatible" provider via the subclass.
 */
class OpenAiClient extends AbstractHttpClient
{
    protected const DEFAULT_BASE = 'https://api.openai.com/v1';

    private const TIMEOUT_SECS = 60;

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function defaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    public function supportsModel(string $model): bool
    {
        return str_starts_with($model, 'gpt-')
            || str_starts_with($model, 'o1-')
            || str_starts_with($model, 'o3-')
            || str_starts_with($model, 'chatgpt-');
    }

    public function complete(PromptBundle $bundle): AiResponse
    {
        $this->assertEgressAllowed();
        $apiKey = $this->requireApiKey();
        $endpoint = $this->resolveBaseUrl(static::DEFAULT_BASE) . '/chat/completions';

        $start = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'timeout' => self::TIMEOUT_SECS,
                'max_redirects' => 0,
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $this->buildPayload($bundle),
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (TransportException $e) {
            throw new AiException(
                'Netzwerkfehler zur OpenAI-API: ' . $e->getMessage(),
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
        // Vision: when images are present the user message becomes a content
        // array (text + image_url data URIs). Text-only calls stay a plain string.
        $userContent = $bundle->userPrompt;
        if ($bundle->images !== []) {
            $userContent = [['type' => 'text', 'text' => $bundle->userPrompt]];
            foreach ($bundle->images as $image) {
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . $image['data']],
                ];
            }
        }

        $payload = [
            'model' => $bundle->model,
            'messages' => [
                ['role' => 'system', 'content' => $bundle->systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens' => $bundle->maxTokens,
        ];

        if (!str_starts_with($bundle->model, 'o1-') && !str_starts_with($bundle->model, 'o3-')) {
            $payload['temperature'] = $bundle->temperature;
        }

        if ($bundle->responseSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $bundle->responseSchema,
            ];
        }

        return $payload;
    }

    private function parseSuccess(string $body, PromptBundle $bundle, int $durationMs): AiResponse
    {
        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            throw new AiException('OpenAI lieferte einen 200 mit nicht-JSON-Body.', AiExceptionKind::InvalidResponse);
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!\is_string($content) || $content === '') {
            $finishReason = (string) ($decoded['choices'][0]['finish_reason'] ?? 'unknown');
            $kind = $finishReason === 'content_filter'
                ? AiExceptionKind::PromptFiltered
                : AiExceptionKind::InvalidResponse;

            throw new AiException('OpenAI lieferte keinen Inhalt (finish_reason: ' . $finishReason . ').', $kind);
        }

        return new AiResponse(
            content: $content,
            tokensIn: (int) ($decoded['usage']['prompt_tokens'] ?? 0),
            tokensOut: (int) ($decoded['usage']['completion_tokens'] ?? 0),
            durationMs: $durationMs,
            provider: $this->getProviderName(),
            model: (string) ($decoded['model'] ?? $bundle->model),
            rawResponse: $body,
        );
    }

    private function throwForErrorStatus(int $statusCode, string $body, ResponseInterface $response): never
    {
        $decoded = json_decode($body, true);
        $errorMsg = \is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
        $errorCode = \is_array($decoded) ? (string) ($decoded['error']['code'] ?? '') : '';

        $retryAfter = $this->readRetryAfter($response);

        $kind = match (true) {
            $statusCode === 401 || $statusCode === 403 => AiExceptionKind::Auth,
            $statusCode === 429 => AiExceptionKind::RateLimit,
            $statusCode >= 400 && $statusCode < 500 => AiExceptionKind::BadRequest,
            $statusCode >= 500 => AiExceptionKind::ServerError,
            default => AiExceptionKind::Unknown,
        };

        if ($errorCode === 'insufficient_quota') {
            $kind = AiExceptionKind::QuotaExceeded;
        }

        // Status + provider error type only — never the raw body (may echo input).
        throw new AiException(
            sprintf('OpenAI %d%s', $statusCode, $errorMsg !== '' ? ': ' . $errorMsg : ''),
            $kind,
            retryAfterSeconds: $retryAfter,
            providerErrorCode: $errorCode !== '' ? $errorCode : null,
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
