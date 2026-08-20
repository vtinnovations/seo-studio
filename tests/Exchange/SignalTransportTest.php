<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Exchange;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\SignalTransport;

/**
 * The two usage signals: exact payload allowlists, the fixed destination, and
 * the guarantee that the licence key never reaches a log.
 */
final class SignalTransportTest extends TestCase
{
    public function testInvocationPayloadCarriesOnlyProjectAndDomain(): void
    {
        self::assertSame(
            ['project' => PackagePolicy::PROJECT, 'domain' => 'example.com'],
            SignalTransport::invocationPayload('example.com'),
        );
    }

    public function testModuleEntryPayloadCarriesOnlyDomainAndKey(): void
    {
        self::assertSame(
            ['domain' => 'example.com', 'key' => 'SS-PRO-0001-ABCD'],
            SignalTransport::moduleEntryPayload('example.com', 'SS-PRO-0001-ABCD'),
        );
    }

    public function testInvocationSignalNeverCarriesAKey(): void
    {
        $captured = [];
        $transport = $this->transport($captured);

        $transport->invocation('example.com');

        self::assertCount(1, $captured);
        self::assertSame('https://www.v-t.one/rest/api/v1/log-envoke', $captured[0]['url']);
        self::assertSame(Endpoint::signal(), $captured[0]['url']);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $captured[0]['options']['body'], true);

        self::assertSame(['project', 'domain'], array_keys($body));
        self::assertArrayNotHasKey('key', $body);
    }

    public function testModuleEntrySignalSendsTheKeyToTheFixedEndpointOnly(): void
    {
        $captured = [];
        $transport = $this->transport($captured);

        $transport->moduleEntry('example.com', 'SS-PRO-0001-ABCD');

        self::assertCount(1, $captured);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $captured[0]['options']['body'], true);

        self::assertSame(['domain', 'key'], array_keys($body));
        self::assertSame('SS-PRO-0001-ABCD', $body['key']);
    }

    public function testTransportIsHardened(): void
    {
        $captured = [];
        $this->transport($captured)->invocation('example.com');

        $options = $captured[0]['options'];

        self::assertSame(0, $options['max_redirects']);
        self::assertTrue($options['verify_peer']);
        self::assertTrue($options['verify_host']);
        self::assertLessThanOrEqual(5, $options['max_duration']);
    }

    public function testNothingIsSentWithoutAKey(): void
    {
        $captured = [];
        $this->transport($captured)->moduleEntry('example.com', '');

        self::assertSame([], $captured);
    }

    public function testTheKeyNeverReachesTheLog(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };

        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 204]));
        $transport = new SignalTransport($client, new OperationLog($logger), false);

        $transport->moduleEntry('example.com', 'SS-PRO-0001-ABCD');

        $serialized = (string) json_encode($logger->records);

        self::assertStringNotContainsString('SS-PRO-0001-ABCD', $serialized);
        self::assertStringNotContainsString('key', $serialized);
        self::assertStringContainsString('module_entry', $serialized);
    }

    public function testEndpointFailureIsSilentAndHasNoReturnValue(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 500]));
        $transport = new SignalTransport($client, new OperationLog(new \Psr\Log\NullLogger()), false);

        $this->expectNotToPerformAssertions();

        $transport->invocation('example.com');
    }

    /**
     * @param list<array{url: string, options: array<string, mixed>}> $captured
     */
    private function transport(array &$captured): SignalTransport
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = ['url' => $url, 'options' => $options];

            return new MockResponse('', ['http_code' => 204]);
        });

        return new SignalTransport($client, new OperationLog(new \Psr\Log\NullLogger()), false);
    }
}
