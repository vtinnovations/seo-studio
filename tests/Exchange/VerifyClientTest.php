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
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\VerifyClient;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * Outbound activation/refresh: the exact packet, the fixed destination, the
 * transport hardening and the response checks that precede any trust decision.
 *
 * No test contacts a real endpoint — every request is served by MockHttpClient.
 */
final class VerifyClientTest extends TestCase
{
    use PackageFixture;

    private const NOW = 1784880547;

    public function testActivationSendsExactlyTheDocumentedFields(): void
    {
        $captured = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = ['method' => $method, 'url' => $url, 'options' => $options];

            return $this->successResponse((string) json_decode((string) $options['body'], true)['request_id']);
        });

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'SS-PRO-0001-ABCD', 'example.com', null, self::NOW);

        self::assertTrue($outcome->hasPackage());
        self::assertNotNull($captured);
        self::assertSame('POST', $captured['method']);
        self::assertSame(Endpoint::verify(), $captured['url']);
        self::assertSame('https://www.v-t.one/api/v1/verify', $captured['url']);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $captured['options']['body'], true);

        self::assertSame([
            'action',
            'project',
            'project_slug',
            'product_id',
            'license_key',
            'domain',
            'request_id',
            'timestamp',
            'nonce',
        ], array_keys($body));

        self::assertSame('activate', $body['action']);
        self::assertSame(PackagePolicy::PROJECT, $body['project']);
        self::assertSame(PackagePolicy::PRODUCT_ID, $body['product_id']);
        self::assertSame('example.com', $body['domain']);
        self::assertArrayNotHasKey('current_license_version', $body);
    }

    public function testRefreshSendsActionRefreshAndCurrentVersion(): void
    {
        $captured = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return $this->successResponse((string) json_decode((string) $options['body'], true)['request_id']);
        });

        (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_REFRESH, 'SS-PRO-0001-ABCD', 'example.com', 7, self::NOW);

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $captured['body'], true);

        self::assertSame('refresh', $body['action']);
        self::assertSame(7, $body['current_license_version']);
    }

    public function testTransportIsHardened(): void
    {
        $captured = null;

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured = $options;

            return $this->successResponse((string) json_decode((string) $options['body'], true)['request_id']);
        });

        (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertSame(0, $captured['max_redirects']);
        self::assertTrue($captured['verify_peer']);
        self::assertTrue($captured['verify_host']);
        self::assertLessThanOrEqual(20, $captured['max_duration']);
        self::assertLessThanOrEqual(10, $captured['timeout']);
    }

    public function testNonceAndRequestIdChangePerAttempt(): void
    {
        $bodies = [];

        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$bodies): MockResponse {
            $bodies[] = json_decode((string) $options['body'], true);

            return new MockResponse('{}', ['http_code' => 500]);
        });

        $verify = new VerifyClient($client, new OperationLog(new NullLogger()));
        $verify->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);
        $verify->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertNotSame($bodies[0]['nonce'], $bodies[1]['nonce']);
        self::assertNotSame($bodies[0]['request_id'], $bodies[1]['request_id']);
    }

    public function testUncorrelatedResponseIsRejected(): void
    {
        $client = new MockHttpClient(fn (): MockResponse => $this->successResponse('someone-elses-id'));

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertFalse($outcome->hasPackage());
        self::assertSame('correlation_mismatch', $outcome->category);
    }

    public function testExcessiveServerSkewIsRejected(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $id = (string) json_decode((string) $options['body'], true)['request_id'];

            return $this->successResponse($id, self::NOW + 86400);
        });

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertSame('server_time_skew', $outcome->category);
    }

    public function testNonJsonResponseIsRejectedBeforeParsing(): void
    {
        $client = new MockHttpClient(new MockResponse('<html>error</html>', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'text/html'],
        ]));

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertSame('response_malformed', $outcome->category);
    }

    public function testOversizedResponseIsRejected(): void
    {
        $client = new MockHttpClient(new MockResponse(str_repeat('a', 70000), [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertSame('response_malformed', $outcome->category);
    }

    public function testServerErrorIsATransportFailureSoStateSurvives(): void
    {
        $client = new MockHttpClient(new MockResponse('{}', [
            'http_code' => 503,
            'response_headers' => ['content-type' => 'application/json'],
        ]));

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_REFRESH, 'k', 'example.com', 7, self::NOW);

        self::assertSame('transport_failure', $outcome->category);
        self::assertFalse($outcome->hasPackage());
    }

    public function testAuthenticatedDenialIsReportedWithoutAPackage(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $id = (string) json_decode((string) $options['body'], true)['request_id'];

            return new MockResponse(
                (string) json_encode(['status' => 'invalid', 'request_id' => $id, 'server_time' => self::NOW]),
                ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
            );
        });

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_ACTIVATE, 'k', 'example.com', null, self::NOW);

        self::assertSame('denied_by_vendor', $outcome->category);
        self::assertFalse($outcome->hasPackage());
    }

    public function testNetworkExceptionKeepsStateAndReportsTransportFailure(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('connection refused');
        });

        $outcome = (new VerifyClient($client, new OperationLog(new NullLogger())))
            ->exchange(VerifyClient::ACTION_REFRESH, 'k', 'example.com', 7, self::NOW);

        self::assertSame('transport_failure', $outcome->category);
    }

    private function successResponse(string $requestId, int $serverTime = self::NOW): MockResponse
    {
        $package = $this->package();

        return new MockResponse(
            (string) json_encode([
                'status' => 'valid',
                'request_id' => $requestId,
                'server_time' => $serverTime,
                'license_payload_b64' => $package['payload'],
                'integrity' => $package['envelope'],
            ]),
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
        );
    }
}
