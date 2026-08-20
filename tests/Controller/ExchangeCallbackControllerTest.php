<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SeoStudio\Controller\ExchangeCallbackController;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\Exchange\InboundRequestCheck;
use VTinnovations\SeoStudio\Exchange\Journal;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;
use VTinnovations\SeoStudio\Exchange\VerifyClient;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * End-to-end HTTP contract of the public updater endpoint.
 *
 * Everything except the database and the outbound HTTP client is REAL: real
 * request-signature authentication, real package verification, real atomic
 * store, real entitlement evaluation. The only doubles are the DBAL connection
 * (journal rows) and MockHttpClient, which is never called on this path.
 */
final class ExchangeCallbackControllerTest extends TestCase
{
    use PackageFixture;

    private string $projectDir = '';

    private int $now = 0;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-callback-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);

        // The handler stamps its own clock, so the signed metadata must use it too.
        $this->now = time();
    }

    protected function tearDown(): void
    {
        $directory = $this->projectDir . '/var/seostudio/provisioning';

        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
        @rmdir($this->projectDir . '/var/seostudio');
        @rmdir($this->projectDir . '/var');
        @rmdir($this->projectDir);
    }

    public function testGetReturns405WithAllowHeaderNotA404(): void
    {
        $response = $this->controller()(Request::create(Endpoint::updaterPath(), 'GET'));

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        self::assertSame('POST', $response->headers->get('Allow'));
    }

    public function testWrongMediaTypeReturns415(): void
    {
        $request = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], '{}');
        $request->headers->set('Content-Type', 'text/plain');

        self::assertSame(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, $this->controller()($request)->getStatusCode());
    }

    public function testOversizedBodyReturns413(): void
    {
        $request = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], str_repeat('x', InboundRequestCheck::MAX_BODY_BYTES + 10));
        $request->headers->set('Content-Type', 'application/json');

        self::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $this->controller()($request)->getStatusCode());
    }

    public function testUnsignedPostIsRejectedGenerically(): void
    {
        $request = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], (string) json_encode(['action' => 'license_update']));
        $request->headers->set('Content-Type', 'application/json');

        $response = $this->controller()($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(['status' => 'unauthorized'], json_decode((string) $response->getContent(), true));
    }

    public function testValidSignedPushIsAppliedAtomically(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $response = $this->controller($store, $this->connection())($this->signedPush(['license_version' => 9]));

        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true);
        self::assertSame('updated', $body['status']);
        self::assertSame(9, $body['license_version']);

        // The stored bytes are exactly what was signed, and they verify.
        $record = $store->load();
        self::assertNotNull($record);
        self::assertSame(9, $record->version());
        self::assertSame(md5($record->bytes), $record->envelopeDigest());
    }

    public function testExactRetryIsIdempotentAndDoesNotApplyTwice(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        // First call claims the request id; the mocked journal then reports the
        // same id with the same body digest, which is an exact retry.
        $connection = $this->connection(existingRow: null);
        $this->controller($store, $connection)($this->signedPush(['license_version' => 9]));

        $retryConnection = $this->connection(existingRow: [
            'bodyDigest' => $this->lastBodyDigest,
            'appliedVersion' => 9,
        ]);

        $response = $this->controller($store, $retryConnection)($this->signedPush(['license_version' => 9]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('already_processed', json_decode((string) $response->getContent(), true)['status']);
    }

    public function testSameRequestIdWithDifferentContentIsAConflict(): void
    {
        $connection = $this->connection(existingRow: ['bodyDigest' => hash('sha256', 'something-else'), 'appliedVersion' => 9]);

        $response = $this->controller(new ProvisioningStore($this->projectDir), $connection)($this->signedPush());

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testReplayedNonceIsRejected(): void
    {
        $connection = $this->connection(nonceOwner: 'another-request-id');

        $response = $this->controller(new ProvisioningStore($this->projectDir), $connection)($this->signedPush());

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testOlderVersionCannotRollBackStoredState(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        // Store version 9 first.
        $this->controller($store, $this->connection())($this->signedPush(['license_version' => 9]));
        self::assertSame(9, $store->load()?->version());

        // Then attempt version 7.
        $response = $this->controller($store, $this->connection())($this->signedPush(['license_version' => 7], 'req-older'));

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame(9, $store->load()?->version(), 'the newer state must survive');
    }

    public function testSameVersionPushIsRejectedBecauseAPushMustBeNewer(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $this->controller($store, $this->connection())($this->signedPush(['license_version' => 9]));
        $response = $this->controller($store, $this->connection())($this->signedPush(['license_version' => 9], 'req-same'));

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testTamperedPayloadIsRejectedAndLeavesNoState(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $package = $this->package(['license_version' => 9]);

        // Re-sign the HTTP request around a payload whose bytes no longer match
        // the envelope digest.
        $body = $this->pushBody($package, 'req-1');
        $body['license_payload_b64'] = base64_encode($package['bytes'] . ' ');

        $response = $this->controller($store, $this->connection())($this->signRequest($body, 'req-1'));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertFalse($store->exists());

        $content = (string) $response->getContent();
        self::assertStringNotContainsString('digest', $content);
        self::assertStringNotContainsString('md5', $content);
    }

    public function testPushForAnUnconfiguredHostIsRejected(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $controller = $this->controller($store, $this->connection(), $this->inventory(['different.example.org'], 'different.example.org'));
        $response = $controller($this->signedPush(['license_version' => 9]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertFalse($store->exists());
    }

    private string $lastBodyDigest = '';

    /**
     * @param array<string, mixed> $overrides
     */
    private function signedPush(array $overrides = [], string $requestId = 'req-1'): Request
    {
        return $this->signRequest($this->pushBody($this->package($overrides), $requestId), $requestId);
    }

    /**
     * @param array{payload: string, envelope: \stdClass, bytes: string, document: array<string, mixed>} $package
     *
     * @return array<string, mixed>
     */
    private function pushBody(array $package, string $requestId): array
    {
        return [
            'action' => 'license_update',
            'project' => PackagePolicy::PROJECT,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'product_id' => PackagePolicy::PRODUCT_ID,
            'domain' => 'example.com',
            'request_id' => $requestId,
            'timestamp' => $this->now,
            'nonce' => 'nonce-' . $requestId,
            'license_payload_b64' => $package['payload'],
            'integrity' => $package['envelope'],
        ];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function signRequest(array $body, string $requestId): Request
    {
        $raw = (string) json_encode($body, JSON_UNESCAPED_SLASHES);
        $this->lastBodyDigest = hash('sha256', $raw);

        $message = implode("\n", [
            'POST',
            Endpoint::updaterPath(),
            $requestId,
            (string) $this->now,
            'nonce-' . $requestId,
            $this->lastBodyDigest,
        ]);

        $request = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], $raw);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-VT-Request-ID', $requestId);
        $request->headers->set('X-VT-Timestamp', (string) $this->now);
        $request->headers->set('X-VT-Nonce', 'nonce-' . $requestId);
        $request->headers->set('X-VT-Key-ID', 'test-key');
        $request->headers->set('X-VT-Signature', $this->sign($message));

        return $request;
    }

    /**
     * @param array<string, mixed>|null $existingRow
     */
    private function connection(?array $existingRow = null, ?string $nonceOwner = null): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($nonceOwner ?? false);
        $connection->method('fetchAssociative')->willReturn($existingRow ?? false);
        $connection->method('insert')->willReturn(1);
        $connection->method('update')->willReturn(1);
        $connection->method('executeStatement')->willReturn(0);

        return $connection;
    }

    private function controller(
        ?ProvisioningStore $store = null,
        ?Connection $connection = null,
        mixed $inventory = null,
    ): ExchangeCallbackController {
        $store ??= new ProvisioningStore($this->projectDir);
        $connection ??= $this->connection();
        $inventory ??= $this->inventory();

        $ring = $this->testRing();
        $verifier = $this->testVerifier();
        $acceptance = new PackageAcceptance($verifier, $ring, $inventory);
        $log = new OperationLog(new NullLogger());
        $journal = new Journal($connection);

        return new ExchangeCallbackController(
            new InboundRequestCheck($verifier, $ring),
            $journal,
            new ProvisioningWorkflow(
                new VerifyClient(new MockHttpClient(), $log),
                $acceptance,
                $store,
                $inventory,
                new EntitlementEvaluator($store, $acceptance, $inventory),
                $journal,
                $log,
            ),
            $log,
        );
    }
}
