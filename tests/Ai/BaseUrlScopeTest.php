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
use VTinnovations\SeoStudio\Core\Ai\AiClientInterface;
use VTinnovations\SeoStudio\Core\Ai\PromptBundle;
use VTinnovations\SeoStudio\Core\Ai\Provider\AnthropicClient;
use VTinnovations\SeoStudio\Core\Ai\Provider\CompatibleClient;
use VTinnovations\SeoStudio\Core\Ai\Provider\MistralClient;
use VTinnovations\SeoStudio\Core\Ai\Provider\OpenAiClient;

/**
 * The configurable base URL belongs to the "compatible" provider only. Hosted
 * providers always use their own endpoint, so a value left over from trying a
 * compatible gateway can never send their API key to another host.
 */
final class BaseUrlScopeTest extends TestCase
{
    use ProviderTestKit;

    private const CONFIGURED = 'https://gateway.example/v1';

    public function testOpenAiIgnoresTheConfiguredBaseUrl(): void
    {
        self::assertSame(
            'https://api.openai.com/v1/chat/completions',
            $this->requestedUrl(OpenAiClient::class, $this->openAiShapedResponse()),
        );
    }

    public function testAnthropicIgnoresTheConfiguredBaseUrl(): void
    {
        $response = new MockResponse((string) json_encode([
            'model' => 'claude-haiku-4-5',
            'content' => [['type' => 'text', 'text' => 'ok']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]));

        self::assertSame('https://api.anthropic.com/v1/messages', $this->requestedUrl(AnthropicClient::class, $response));
    }

    public function testMistralIgnoresTheConfiguredBaseUrl(): void
    {
        self::assertSame(
            'https://api.mistral.ai/v1/chat/completions',
            $this->requestedUrl(MistralClient::class, $this->openAiShapedResponse()),
        );
    }

    public function testCompatibleUsesTheConfiguredBaseUrl(): void
    {
        self::assertSame(
            self::CONFIGURED . '/chat/completions',
            $this->requestedUrl(CompatibleClient::class, $this->openAiShapedResponse()),
        );
    }

    /**
     * @param class-string<AiClientInterface> $class
     */
    private function requestedUrl(string $class, MockResponse $response): string
    {
        $url = '';
        $http = new MockHttpClient(function (string $method, string $requested) use (&$url, $response): MockResponse {
            $url = $requested;

            return $response;
        });

        /** @var AiClientInterface $client */
        $client = new $class($http, $this->config(['aiBaseUrl' => self::CONFIGURED]), $this->secrets());
        $client->complete(new PromptBundle('system', 'user', $client->defaultModel()));

        return $url;
    }

    private function openAiShapedResponse(): MockResponse
    {
        return new MockResponse((string) json_encode([
            'model' => 'm',
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ]));
    }
}
