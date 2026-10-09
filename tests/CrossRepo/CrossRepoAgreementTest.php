<?php

declare(strict_types=1);

namespace VTinnovations\SeoStudio\Tests\CrossRepo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vtinnovations\LicenseServer\Service\Signing\CanonicalSerializer;
use Vtinnovations\LicenseServer\Service\Signing\Ed25519Signer;
use Vtinnovations\LicenseServer\Service\Update\RequestSigner;
use VTinnovations\SeoStudio\Controller\ExchangeCallbackController;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\EntitlementState;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Core\Security\SignatureVerifier;
use VTinnovations\SeoStudio\Core\Security\TrustAnchor;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\Exchange\InboundRequestCheck;
use VTinnovations\SeoStudio\Exchange\Journal;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;
use VTinnovations\SeoStudio\Exchange\VerifyClient;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * CROSS-REPO AGREEMENT.
 *
 * Every other test in this bundle builds its packets with the bundle's own
 * helpers, so the two halves of the protocol could drift apart and every test
 * would still pass. This one builds them with the LICENCE SERVER'S OWN code —
 * `CanonicalSerializer`, `Ed25519Signer` and `RequestSigner` loaded straight
 * out of that repository — and drives the result through this bundle's real
 * controller. It is the check that catches a protocol change made on one side
 * only, which is the failure this product family keeps producing.
 *
 * Only those three files are loaded, and each is dependency-free: no entities,
 * no Doctrine, no database. `RequestSigner` is then instantiated WITHOUT its
 * constructor, because `signingString()` touches neither dependency — that gets
 * the server's real rule rather than a re-implementation of it.
 *
 * The licence server is a sibling working copy, not a dependency of this
 * package, so these tests SKIP when it is not present. Point VT_LICENSE_SERVER
 * at it to run them from elsewhere. That means a green suite on a machine
 * without it proves less — check the skip count when this matters.
 *
 * The throwaway keypair is generated here. No vendor private key is involved,
 * and no live endpoint is contacted.
 */
final class CrossRepoAgreementTest extends TestCase
{
    use PackageFixture;

    private const KEY_ID = 'xrepo-key';

    private const LICENCE_KEY = 'SS-PRO-XREPO-0001';

    /**
     * What the vendor actually puts in every "project" field.
     *
     * The server reads it off the product record ($product->getTitle()), so it
     * is the catalogue's DISPLAY spelling — not the compact wire form this
     * bundle uses for its own outbound packets. Modelling it as the compact
     * form is what let a byte-for-byte comparison of this field ship: every
     * fixture agreed with the client and nothing agreed with the server.
     */
    private const CATALOGUE_TITLE = 'SEO Studio';

    private string $projectDir = '';

    private string $secret = '';

    private string $public = '';

    private int $now = 0;

    private string $serverRoot = '';

    /**
     * The sibling licence-server working copy, or null when it is not here.
     *
     * Deliberately not configurable beyond one environment variable: the whole
     * value of this test is that it reads the OTHER repository's real source,
     * so a fallback that quietly used a local copy would defeat it.
     */
    private static function locateServer(): ?string
    {
        $candidates = array_filter([
            getenv('VT_LICENSE_SERVER') ?: null,
            \dirname(__DIR__, 3) . '/license-server',
            \dirname(__DIR__, 4) . '/license-server',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate . '/src/Service/Signing/CanonicalSerializer.php')) {
                return rtrim((string) $candidate, '/');
            }
        }

        return null;
    }

    protected function setUp(): void
    {
        $root = self::locateServer();

        if ($root === null) {
            self::markTestSkipped('The licence-server working copy is not available; set VT_LICENSE_SERVER to run the cross-repo checks.');
        }

        $this->serverRoot = $root;

        foreach ([
            '/src/Service/Signing/CanonicalSerializer.php',
            '/src/Service/Signing/Ed25519Signer.php',
            '/src/Service/Update/RequestSigner.php',
        ] as $file) {
            require_once $root . $file;
        }

        $pair = sodium_crypto_sign_seed_keypair(str_repeat("\x7b", \SODIUM_CRYPTO_SIGN_SEEDBYTES));
        $this->secret = sodium_crypto_sign_secretkey($pair);
        $this->public = sodium_crypto_sign_publickey($pair);

        $this->now = time();
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-xrepo-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->projectDir !== '' && is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    /**
     * The canonical form both sides sign over must agree byte for byte. The two
     * implementations even sort differently on paper — the server uses ksort(),
     * the client a recursive uksort() with strcmp() — and they agree only
     * because every field name is non-numeric. Worth not "simplifying" either.
     */
    public function testTheTwoCanonicalSerializersAgreeByteForByte(): void
    {
        $payload = $this->licencePayload();
        unset($payload['signature']);

        $server = (new CanonicalSerializer())->canonicalize($payload);
        $client = \VTinnovations\SeoStudio\Core\Security\CanonicalForm::encode(
            \VTinnovations\SeoStudio\Core\Security\CanonicalForm::decode((string) json_encode($payload)),
        );

        self::assertSame($server, $client);
    }

    /** The six-line request-signature input must be identical on both sides. */
    public function testTheRequestSigningStringMatchesTheServersRule(): void
    {
        $signer = $this->requestSigner();
        $body = '{"action":"license_update"}';

        $expected = implode("\n", [
            'POST',
            Endpoint::updaterPath(),
            'req-x',
            (string) $this->now,
            'nonce-x',
            hash('sha256', $body),
        ]);

        self::assertSame(
            $expected,
            $signer->signingString('POST', Endpoint::updaterPath(), 'req-x', $this->now, 'nonce-x', $body),
        );
    }

    /** A grant built entirely by the server's code is accepted. */
    public function testAServerBuiltGrantIsAccepted(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $response = $this->deliver($store, $this->grant(['example.com'], 5), 'req-grant');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('updated', $this->body($response)['status']);
        self::assertSame(5, $store->load()?->version());
    }

    /**
     * THE POINT OF THE WHOLE EXERCISE: a revocation built by the server's own
     * builders, delivered to the released host, is accepted and switches the
     * product off.
     */
    public function testAServerBuiltRevocationDisablesTheReleasedHost(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory(['example.com'], 'example.com');

        $this->deliver($store, $this->grant(['example.com'], 5), 'req-grant', $inventory);
        self::assertTrue($this->entitlement($store, $inventory)->evaluate($this->now)->licensed);

        // The customer moves the licence: version bumped once, A revoked,
        // license_domains now names only the destination.
        $response = $this->deliver($store, $this->revoke('example.com', ['newsite.example.org'], 6), 'req-revoke', $inventory);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('updated', $this->body($response)['status']);
        self::assertSame(6, $this->body($response)['license_version']);

        $state = $this->entitlement($store, $inventory)->evaluate($this->now);
        self::assertFalse($state->licensed);
        self::assertSame(EntitlementState::REVOKED, $state->status);
    }

    /** The last slot released: an empty signed host set, built by the server. */
    public function testAServerBuiltRevocationWithAnEmptyHostSetIsAccepted(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory(['example.com'], 'example.com');

        $this->deliver($store, $this->grant(['example.com'], 5), 'req-grant', $inventory);
        $response = $this->deliver($store, $this->revoke('example.com', [], 6), 'req-revoke', $inventory);

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($this->entitlement($store, $inventory)->evaluate($this->now)->licensed);
    }

    /**
     * A transfer is settled at ONE revision and stated from both sides, so an
     * installation serving two of the licence's hosts must accept both packets
     * at the same version.
     */
    public function testBothSidesOfATransferAreAcceptedAtTheSameRevision(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory(['a.example.com', 'b.example.com'], 'b.example.com');

        $this->deliver($store, $this->grant(['a.example.com', 'b.example.com'], 5, 'a.example.com'), 'req-grant', $inventory);

        // "a" released; both packets carry version 6.
        $this->deliver($store, $this->revoke('a.example.com', ['b.example.com'], 6), 'req-revoke', $inventory);
        $reissue = $this->deliver($store, $this->grant(['b.example.com'], 6, 'b.example.com'), 'req-reissue', $inventory);

        self::assertSame(200, $reissue->getStatusCode(), 'the kept host must still get its re-issue');
        self::assertTrue($this->entitlement($store, $inventory)->evaluate($this->now)->licensed);
    }

    /**
     * An exact redelivery of the same operation is idempotent.
     *
     * The packet is built ONCE and the identical bytes replayed: rebuilding it
     * calls time() again, which changes the body, which makes the same request
     * id a conflict rather than a replay — and that passes intermittently,
     * only when both calls land in the same second.
     */
    public function testAnExactRedeliveryIsIdempotent(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $request = $this->request($this->grant(['example.com'], 5), 'req-once');

        $first = $this->controller($store, $this->connection(null))($request);
        self::assertSame('updated', $this->body($first)['status']);

        // The journal now reports the same id with the same body digest.
        $replay = $this->controller($store, $this->connection([
            'requestId' => 'req-once',
            'bodyDigest' => hash('sha256', (string) $request->getContent()),
            'result' => 'updated',
            'licenseVersion' => 5,
        ]))($request);

        self::assertSame('already_processed', $this->body($replay)['status']);
    }

    /** A captured pre-transfer packet replayed after the revocation is refused. */
    public function testAReplayedPreTransferPacketCannotUndoTheRevocation(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory(['example.com'], 'example.com');

        $captured = $this->grant(['example.com'], 5);

        $this->deliver($store, $captured, 'req-grant', $inventory);
        $this->deliver($store, $this->revoke('example.com', ['newsite.example.org'], 6), 'req-revoke', $inventory);

        // Same authentic bytes, fresh delivery metadata: a genuine replay.
        $response = $this->deliver($store, $captured, 'req-replay', $inventory);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertFalse($this->entitlement($store, $inventory)->evaluate($this->now)->licensed);
    }

    /** Tampering with one byte of the payload breaks the digest tripwire. */
    public function testATamperedPayloadIsRefused(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $package = $this->grant(['example.com'], 5);

        $bytes = (string) base64_decode((string) $package['license_payload_b64'], true);
        $package['license_payload_b64'] = base64_encode(str_replace('"pro"', '"PRO"', $bytes));

        $response = $this->deliver($store, $package, 'req-tampered');

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        self::assertNull($store->load());
    }

    /** A packet signed by a key that is not pinned is refused. */
    public function testAPacketSignedByAnUnpinnedKeyIsRefused(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $package = $this->grant(['example.com'], 5);

        $foreign = new TrustAnchors([
            new TrustAnchor(self::KEY_ID, 'ed25519', sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()), [
                TrustAnchor::PURPOSE_DOCUMENT,
                TrustAnchor::PURPOSE_ENVELOPE,
                TrustAnchor::PURPOSE_REQUEST,
            ], 0, null),
        ]);

        $response = $this->controller($store, $this->connection(null), null, $foreign)(
            $this->request($package, 'req-foreign'),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    // ------------------------------------------------------------------ drift

    /**
     * FIELD-SET DRIFT. The server's builders are copied in as inert text and
     * their field names extracted at run time, so a field renamed or added over
     * there fails here rather than on a customer's site.
     *
     * The character class must include digits: [a-z_]+ silently misses
     * license_md5 and license_payload_b64, and the diff then points the wrong
     * way.
     */
    public function testTheServersPayloadFieldSetIsTheOneThisClientReads(): void
    {
        $fields = $this->fieldsOf('/src/Service/Signing/SignedLicenseBuilder.php', "\$payload = [");

        $expected = [
            'schema_version', 'project', 'project_slug', 'license_key', 'license_domain',
            'license_domains', 'license_max_domains', 'license_package', 'license_features',
            'license_version', 'license_issued_at', 'license_starts_at', 'license_expires_at',
            'license_lifetime', 'license_verified_at', 'free_available', 'validation_status',
        ];

        self::assertSame($expected, $fields, 'the licence payload field set has drifted');
    }

    public function testTheServersEnvelopeFieldSetIsTheOneThisClientReads(): void
    {
        $fields = $this->fieldsOf('/src/Service/Signing/IntegrityEnvelopeBuilder.php', "\$envelope = [");

        self::assertSame([
            'project', 'project_slug', 'license_version', 'license_md5',
            'generated_at', 'key_id', 'signature_algorithm',
        ], $fields, 'the integrity envelope field set has drifted');
    }

    public function testTheServersRequestBodyFieldSetIsTheOneThisClientReads(): void
    {
        $fields = $this->fieldsOf('/src/Service/Update/UpdatePackageBuilder.php', "\$fields = [");

        self::assertSame([
            'action', 'project', 'project_slug', 'product_id', 'domain',
            'request_id', 'timestamp', 'nonce', 'license_payload_b64', 'integrity',
        ], $fields, 'the updater request body field set has drifted');
    }

    /** The updater path the server posts to must be the route this bundle serves. */
    public function testTheUpdaterPathMatchesTheServersTemplate(): void
    {
        $source = $this->serverSource('/src/Service/Update/UpdatePackageBuilder.php');

        self::assertSame(1, preg_match("/UPDATER_PATH = '([^']+)'/", $source, $m));
        self::assertSame(Endpoint::updaterPath(), sprintf($m[1], PackagePolicy::PROJECT_SLUG));
    }

    /**
     * The status word a revocation carries on the wire must be the one this
     * client recognises as a withdrawal.
     */
    public function testTheServersRevokedStatusIsTheWordThisClientActsOn(): void
    {
        $source = $this->serverSource('/src/Service/Signing/SignedLicenseBuilder.php');

        self::assertSame(1, preg_match("/STATUS_REVOKED\s*=\s*'([^']+)'/", $source, $m));
        self::assertContains($m[1], \VTinnovations\SeoStudio\Core\Config\ProvisioningRecord::STATUSES);
        self::assertSame('revoked', $m[1]);
    }

    /**
     * The server's own conformance rule INVERTS host membership for a
     * revocation, which is the contract this client had to be taught. If that
     * inversion were ever dropped server-side, the negative path here would be
     * dead code.
     */
    public function testTheServerStillInvertsHostMembershipForARevocation(): void
    {
        $source = $this->serverSource('/src/Service/Signing/SignedLicenseBuilder.php');

        self::assertStringContainsString('STATUS_REVOKED === $payload[\'validation_status\']', $source);
        self::assertStringContainsString('is revoked but still listed in license_domains', $source);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Field names from a builder's array literal, in source order.
     *
     * @return list<string>
     */
    private function fieldsOf(string $file, string $anchor): array
    {
        $source = $this->serverSource($file);
        $start = strpos($source, $anchor);
        self::assertNotFalse($start, $anchor . ' not found in ' . $file);

        $end = strpos($source, '];', $start);
        self::assertNotFalse($end);

        preg_match_all("/'([a-z0-9_]+)'\s*=>/", substr($source, $start, $end - $start), $matches);

        return $matches[1];
    }

    /** Source of one licence-server file, read from the sibling working copy. */
    private function serverSource(string $relativePath): string
    {
        $path = $this->serverRoot . $relativePath;
        self::assertFileExists($path, 'the licence server no longer has ' . $relativePath);

        return (string) file_get_contents($path);
    }

    /**
     * A signed grant, built the way the server builds one.
     *
     * @param list<string> $domains
     *
     * @return array<string, mixed>
     */
    private function grant(array $domains, int $version, ?string $target = null): array
    {
        return $this->assemble($this->licencePayload([
            'license_domain' => $target ?? $domains[0],
            'license_domains' => $domains,
            'license_version' => $version,
            'validation_status' => 'valid',
        ]));
    }

    /**
     * A signed revocation, built the way the server's buildRevocation() does:
     * same key, package, features and term, only the status and the authorised
     * set differ, and the target is deliberately absent from that set.
     *
     * @param list<string> $remaining
     *
     * @return array<string, mixed>
     */
    private function revoke(string $released, array $remaining, int $version): array
    {
        return $this->assemble($this->licencePayload([
            'license_domain' => $released,
            'license_domains' => $remaining,
            'license_version' => $version,
            'validation_status' => 'revoked',
        ]));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function licencePayload(array $overrides = []): array
    {
        $serializer = new CanonicalSerializer();
        $signer = new Ed25519Signer();

        $payload = array_merge([
            'schema_version' => 2,
            'project' => self::CATALOGUE_TITLE,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'license_key' => self::LICENCE_KEY,
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
            'license_max_domains' => 9999,
            'license_package' => 'pro',
            'license_features' => [],
            'license_version' => 5,
            'license_issued_at' => $this->now - 86400,
            'license_starts_at' => $this->now - 86400,
            'license_expires_at' => $this->now + 86400 * 365,
            'license_lifetime' => false,
            'license_verified_at' => $this->now,
            'free_available' => false,
            'validation_status' => 'valid',
        ], $overrides);

        $payload['signature'] = $signer->sign($serializer->canonicalize($payload), $this->secret);

        return $payload;
    }

    /**
     * Wraps a signed licence in the request body, exactly as
     * UpdatePackageBuilder::assemble() does.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function assemble(array $payload): array
    {
        $serializer = new CanonicalSerializer();
        $signer = new Ed25519Signer();

        $json = $serializer->encode($payload);

        $envelope = [
            'project' => self::CATALOGUE_TITLE,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'license_version' => (int) $payload['license_version'],
            'license_md5' => md5($json),
            'generated_at' => $this->now,
            'key_id' => self::KEY_ID,
            'signature_algorithm' => 'ed25519',
        ];
        $envelope['signature'] = $signer->sign($serializer->canonicalize($envelope), $this->secret);

        return [
            'action' => 'license_update',
            'project' => self::CATALOGUE_TITLE,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'product_id' => PackagePolicy::PRODUCT_ID,
            'domain' => (string) $payload['license_domain'],
            'license_payload_b64' => base64_encode($json),
            'integrity' => $envelope,
        ];
    }

    /**
     * @param array<string, mixed> $package
     */
    private function request(array $package, string $requestId): Request
    {
        $serializer = new CanonicalSerializer();

        $nonce = 'nonce-' . $requestId;
        $body = array_merge($package, [
            'request_id' => $requestId,
            'timestamp' => $this->now,
            'nonce' => $nonce,
        ]);

        // Serialized once: the request signature covers a hash of exactly these
        // bytes, so the delivery must send this string and not re-encode.
        $raw = $serializer->encode($body);
        $path = Endpoint::updaterPath();

        $signature = (new Ed25519Signer())->sign(
            $this->requestSigner()->signingString('POST', $path, $requestId, $this->now, $nonce, $raw),
            $this->secret,
        );

        $request = Request::create($path, 'POST', [], [], [], [], $raw);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-VT-Request-ID', $requestId);
        $request->headers->set('X-VT-Timestamp', (string) $this->now);
        $request->headers->set('X-VT-Nonce', $nonce);
        $request->headers->set('X-VT-Key-ID', self::KEY_ID);
        $request->headers->set('X-VT-Signature', $signature);

        return $request;
    }

    /**
     * @param array<string, mixed> $package
     */
    private function deliver(
        ProvisioningStore $store,
        array $package,
        string $requestId,
        ?HostInventory $inventory = null,
    ): Response {
        return $this->controller($store, $this->connection(null), $inventory)($this->request($package, $requestId));
    }

    /**
     * signingString() touches neither constructor dependency, so this gets the
     * server's real rule instead of a copy of it.
     */
    private function requestSigner(): RequestSigner
    {
        /** @var RequestSigner $signer */
        $signer = (new \ReflectionClass(RequestSigner::class))->newInstanceWithoutConstructor();

        return $signer;
    }

    private function ring(): TrustAnchors
    {
        return new TrustAnchors([
            new TrustAnchor(self::KEY_ID, 'ed25519', $this->public, [
                TrustAnchor::PURPOSE_DOCUMENT,
                TrustAnchor::PURPOSE_ENVELOPE,
                TrustAnchor::PURPOSE_REQUEST,
            ], 0, null),
        ]);
    }

    private function entitlement(ProvisioningStore $store, HostInventory $inventory): EntitlementEvaluator
    {
        $ring = $this->ring();

        return new EntitlementEvaluator(
            $store,
            new PackageAcceptance(new SignatureVerifier($ring), $ring, $inventory, $store),
            $inventory,
        );
    }

    /**
     * @param array<string, mixed>|null $existingRow
     */
    private function connection(?array $existingRow): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn($existingRow ?? false);
        $connection->method('insert')->willReturn(1);
        $connection->method('update')->willReturn(1);
        $connection->method('executeStatement')->willReturn(0);

        return $connection;
    }

    private function controller(
        ProvisioningStore $store,
        Connection $connection,
        ?HostInventory $inventory = null,
        ?TrustAnchors $ring = null,
    ): ExchangeCallbackController {
        $inventory ??= $this->inventory(['example.com'], 'example.com');
        $ring ??= $this->ring();
        $verifier = new SignatureVerifier($ring);
        $acceptance = new PackageAcceptance($verifier, $ring, $inventory, $store);
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

    /**
     * @return array<string, mixed>
     */
    private function body(Response $response): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $response->getContent(), true);

        return $decoded;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
