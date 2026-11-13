<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Ai;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VTinnovations\SeoStudio\Core\Ai\AiException;
use VTinnovations\SeoStudio\Core\Ai\AiExceptionKind;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Ai\Provider\MistralClient;

/**
 * Mistral AI provider: fixed endpoint, request shape, response parsing and
 * error mapping. No test contacts a real endpoint.
 */
final class MistralClientTest extends TestCase
{
    use ProviderTestKit;

    private const SCHEMA = [
        'name' => 'meta_result',
        'schema' => [
            'type' => 'object',
            'properties' => ['pageTitle' => ['type' => 'string']],
            'required' => ['pageTitle'],
            'additionalProperties' => false,
        ],
    ];

    public function testIdentityAndModels(): void
    {
        $client = new MistralClient(new MockHttpClient(), $this->config(), $this->secrets());

        self::assertSame('mistral', $client->getProviderName());
        self::assertSame('mistral-small-latest', $client->defaultModel());
        self::assertTrue($client->supportsModel('mistral-large-latest'));
        self::assertTrue($client->supportsModel('ministral-8b-latest'));
        self::assertTrue($client->supportsModel('magistral-medium-latest'));
        self::assertTrue($client->supportsModel('pixtral-large-latest'));
        self::assertFalse($client->supportsModel('gpt-4o-mini'));
        self::assertFalse($client->supportsModel('claude-haiku-4-5'));
    }

    public function testSendsTheDocumentedRequestToTheFixedEndpoint(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return $this->success('{"pageTitle":"Hallo"}');
        });

        // A leftover "compatible" base URL must never redirect the Mistral key.
        $client = new MistralClient($http, $this->config(['aiBaseUrl' => 'https://attacker.example/v1']), $this->secrets('mistral-secret'));
        $client->complete($this->bundle(responseSchema: self::SCHEMA));

        self::assertNotNull($captured);
        self::assertSame('POST', $captured['method']);
        self::assertSame('https://api.mistral.ai/v1/chat/completions', $captured['url']);
        self::assertContains('Authorization: Bearer mistral-secret', $captured['options']['headers']);
        self::assertSame(0, $captured['options']['max_redirects']);

        $body = json_decode((string) $captured['options']['body'], true);
        self::assertSame('mistral-small-latest', $body['model']);
        self::assertSame(0.3, $body['temperature']);
        self::assertSame(256, $body['max_tokens']);
        self::assertSame('system', $body['messages'][0]['role']);
        self::assertSame('Sag hallo.', $body['messages'][1]['content']);
        self::assertSame(['type' => 'json_schema', 'json_schema' => self::SCHEMA], $body['response_format']);
    }

    public function testImagesAreSentAsPlainStringDataUris(): void
    {
        $captured = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = json_decode((string) $options['body'], true);

            return $this->success('Ein Hund im Park.');
        });

        $client = new MistralClient($http, $this->config(), $this->secrets());
        $client->complete($this->bundle(images: [['mime' => 'image/png', 'data' => 'QUJD']]));

        $content = $captured['messages'][1]['content'];
        self::assertSame(['type' => 'text', 'text' => 'Sag hallo.'], $content[0]);
        self::assertSame(['type' => 'image_url', 'image_url' => 'data:image/png;base64,QUJD'], $content[1]);
    }

    public function testParsesContentAndUsage(): void
    {
        $client = new MistralClient(
            new MockHttpClient(fn (): MockResponse => $this->success('Hallo Welt', 12, 3)),
            $this->config(),
            $this->secrets(),
        );

        $response = $client->complete($this->bundle());

        self::assertSame('Hallo Welt', $response->content);
        self::assertSame(12, $response->tokensIn);
        self::assertSame(3, $response->tokensOut);
        self::assertSame('mistral', $response->provider);
        self::assertSame('mistral-small-2506', $response->model);
    }

    public function testJoinsTextChunksFromReasoningModels(): void
    {
        $body = json_encode([
            'model' => 'magistral-medium-latest',
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => [
                    ['type' => 'thinking', 'thinking' => [['type' => 'text', 'text' => 'hidden']]],
                    ['type' => 'text', 'text' => '{"pageTitle":'],
                    ['type' => 'text', 'text' => '"Hallo"}'],
                ]],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7],
        ]);

        $client = new MistralClient(new MockHttpClient(new MockResponse((string) $body)), $this->config(), $this->secrets());

        self::assertSame('{"pageTitle":"Hallo"}', $client->complete($this->bundle())->content);
    }

    public function testEmptyContentIsAnInvalidResponse(): void
    {
        $body = json_encode(['choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']]]);
        $client = new MistralClient(new MockHttpClient(new MockResponse((string) $body)), $this->config(), $this->secrets());

        $e = $this->expectAiException(fn () => $client->complete($this->bundle()));
        self::assertSame(AiExceptionKind::InvalidResponse, $e->kind);
    }

    public function testUnauthorizedMapsToAuth(): void
    {
        $e = $this->failWith(401, '{"message":"Unauthorized","request_id":"abc"}');

        self::assertSame(AiExceptionKind::Auth, $e->kind);
        self::assertStringContainsString('Mistral AI 401', $e->getMessage());
        self::assertStringContainsString('Unauthorized', $e->getMessage());
    }

    public function testRateLimitCarriesRetryAfter(): void
    {
        $e = $this->failWith(429, '{"message":"Requests rate limit exceeded"}', ['retry-after: 7']);

        self::assertSame(AiExceptionKind::RateLimit, $e->kind);
        self::assertSame(7, $e->retryAfterSeconds);
    }

    public function testStructuredValidationMessageIsNotEchoed(): void
    {
        $e = $this->failWith(422, (string) json_encode([
            'object' => 'error',
            'message' => ['detail' => [['msg' => 'bad', 'input' => 'SECRET PAGE TEXT']]],
            'type' => 'invalid_request_message_error',
            'code' => null,
        ]));

        self::assertSame(AiExceptionKind::BadRequest, $e->kind);
        self::assertSame('invalid_request_message_error', $e->providerErrorCode);
        self::assertStringNotContainsString('SECRET PAGE TEXT', $e->getMessage());
    }

    public function testServerErrorIsRetryable(): void
    {
        $e = $this->failWith(503, '{"object":"error","message":"Service unavailable","type":"service_unavailable","code":"3505"}');

        self::assertSame(AiExceptionKind::ServerError, $e->kind);
        self::assertSame('3505', $e->providerErrorCode);
        self::assertTrue($e->isRetryable());
    }

    public function testEgressSwitchBlocksBeforeAnyNetworkCall(): void
    {
        $http = new MockHttpClient(function (): MockResponse {
            self::fail('No request may be sent while external calls are blocked.');
        });

        $client = new MistralClient($http, $this->config(['noExternalCalls' => true]), $this->secrets());

        $e = $this->expectAiException(fn () => $client->complete($this->bundle()));
        self::assertSame(AiExceptionKind::EgressBlocked, $e->kind);
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testMissingKeyIsAnAuthError(): void
    {
        $http = new MockHttpClient();
        $client = new MistralClient($http, $this->config(), $this->secrets(null));

        $e = $this->expectAiException(fn () => $client->complete($this->bundle()));
        self::assertSame(AiExceptionKind::Auth, $e->kind);
        self::assertSame(0, $http->getRequestsCount());
    }

    /**
     * @param array<string, mixed>|null               $responseSchema
     * @param list<array{mime: string, data: string}> $images
     */
    private function bundle(?array $responseSchema = null, array $images = []): PromptBundle
    {
        return new PromptBundle(
            systemPrompt: 'Du bist ein Test.',
            userPrompt: 'Sag hallo.',
            model: 'mistral-small-latest',
            temperature: 0.3,
            maxTokens: 256,
            responseSchema: $responseSchema,
            purpose: 'test',
            images: $images,
        );
    }

    private function success(string $content, int $in = 1, int $out = 1): MockResponse
    {
        return new MockResponse((string) json_encode([
            'id' => 'cmpl-1',
            'object' => 'chat.completion',
            'model' => 'mistral-small-2506',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $content],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out, 'total_tokens' => $in + $out],
        ]));
    }

    /**
     * @param list<string> $headers
     */
    private function failWith(int $status, string $body, array $headers = []): AiException
    {
        $client = new MistralClient(
            new MockHttpClient(new MockResponse($body, ['http_code' => $status, 'response_headers' => $headers])),
            $this->config(),
            $this->secrets(),
        );

        return $this->expectAiException(fn () => $client->complete($this->bundle()));
    }

    private function expectAiException(callable $call): AiException
    {
        try {
            $call();
        } catch (AiException $e) {
            return $e;
        }

        self::fail('Expected an AiException.');
    }
}
