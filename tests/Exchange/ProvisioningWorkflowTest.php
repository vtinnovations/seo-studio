<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Exchange;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Exchange\Journal;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;
use VTinnovations\SeoStudio\Exchange\VerifyClient;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * The administrator operations end to end, with the vendor served by
 * MockHttpClient so a real endpoint is never contacted.
 *
 * The focus here is the rollback rule, which is the one place where the state
 * ALREADY on disk can reject a package the vendor has just approved.
 */
final class ProvisioningWorkflowTest extends TestCase
{
    use PackageFixture;

    private const NOW = 1784900000;

    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-workflow-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->projectDir !== '' && is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    /**
     * A replacement purchase gets a brand-new licence whose own version counter
     * starts at 1, while the outgoing licence may sit at any higher number.
     * Rejecting that as a rollback rejected activations V-T.ONE had already
     * approved, which is exactly the "vendor says success, nothing activates"
     * report this test pins down.
     */
    public function testDifferentLicenceKeyActivatesEvenWhenTheStoredVersionIsHigher(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $this->activate($store, 'SS-PRO-OLD-0001', 12);
        self::assertSame(12, $store->load()?->version());

        $result = $this->activate($store, 'SS-PRO-NEW-9999', 1);

        self::assertSame(ProvisioningWorkflow::OK, $result);
        self::assertSame(1, $store->load()?->version());
        self::assertSame('SS-PRO-NEW-9999', $store->load()?->licenceKey());
    }

    /**
     * The anti-replay guard itself must survive: an OLDER package for the SAME
     * licence is still refused, so a captured earlier response cannot restore a
     * longer expiry or a wider host set.
     */
    public function testSameLicenceKeyStillRefusesAnOlderVersion(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $this->activate($store, 'SS-PRO-OLD-0001', 12);

        $result = $this->activate($store, 'SS-PRO-OLD-0001', 7);

        self::assertSame(PackageAcceptance::ROLLBACK, $result);
        self::assertSame(12, $store->load()?->version(), 'the previously valid state must survive untouched');
    }

    public function testSameLicenceKeyAcceptsTheSameVersionAgain(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $this->activate($store, 'SS-PRO-OLD-0001', 12);

        self::assertSame(ProvisioningWorkflow::OK, $this->activate($store, 'SS-PRO-OLD-0001', 12));
    }

    /**
     * A refresh that carries a replacement key is the same supersession case.
     */
    public function testRefreshWithAReplacementKeyIsNotARollback(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $this->activate($store, 'SS-PRO-OLD-0001', 12);

        $workflow = $this->workflow($store, $this->package([
            'license_key' => 'SS-PRO-NEW-9999',
            'license_version' => 2,
        ]));

        self::assertSame(ProvisioningWorkflow::OK, $workflow->refresh('SS-PRO-NEW-9999', self::NOW));
        self::assertSame(2, $store->load()?->version());
    }

    private function activate(ProvisioningStore $store, string $licenceKey, int $version): string
    {
        $package = $this->package([
            'license_key' => $licenceKey,
            'license_version' => $version,
        ]);

        return $this->workflow($store, $package)->activate($licenceKey, self::NOW);
    }

    /**
     * @param array{payload: string, envelope: \stdClass, bytes: string, document: array<string, mixed>} $package
     */
    private function workflow(ProvisioningStore $store, array $package): ProvisioningWorkflow
    {
        $inventory = $this->inventory(['example.com'], 'example.com');
        $acceptance = new PackageAcceptance($this->testVerifier(), $this->testRing(), $inventory);
        $log = new OperationLog(new NullLogger());

        $client = new MockHttpClient(
            fn (string $method, string $url, array $options): MockResponse => new MockResponse(
                (string) json_encode([
                    'status' => 'valid',
                    'request_id' => (string) json_decode((string) $options['body'], true)['request_id'],
                    'server_time' => self::NOW,
                    'license_payload_b64' => $package['payload'],
                    'integrity' => $package['envelope'],
                ]),
                ['response_headers' => ['content-type' => 'application/json']],
            ),
        );

        return new ProvisioningWorkflow(
            new VerifyClient($client, $log),
            $acceptance,
            $store,
            $inventory,
            new EntitlementEvaluator($store, $acceptance, $inventory),
            new Journal($this->createMock(Connection::class)),
            $log,
        );
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
