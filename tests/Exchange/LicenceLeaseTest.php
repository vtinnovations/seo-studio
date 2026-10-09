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
use VTinnovations\SeoStudio\Core\Config\EntitlementState;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Exchange\Journal;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;
use VTinnovations\SeoStudio\Exchange\VerifyClient;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * The lease, which is the half of a domain transfer that actually ENFORCES.
 *
 * A vendor push closes the hole only when it arrives, and the installation that
 * lost the licence is exactly the one that may be offline, behind changed DNS,
 * firewalled, or deliberately dropping our requests. Dropping them is what
 * somebody exploiting the transfer does, and no amount of retrying reaches an
 * endpoint that answers nothing.
 *
 * So entitlement is a lease: it has to be renewed against the vendor, and an
 * installation that is never reachable runs out of it on its own. Both
 * deadlines are computed from fields INSIDE the signature, which is what puts
 * them out of the site owner's reach.
 */
final class LicenceLeaseTest extends TestCase
{
    use PackageFixture;

    private const NOW = 1784900000;

    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-lease-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->projectDir !== '' && is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    // ------------------------------------------------------------ the deadline

    /**
     * With no signed lease policy the fallback applies, measured from when the
     * vendor last confirmed the licence.
     */
    public function testTheFallbackLeaseIsMeasuredFromTheSignedVerifiedAt(): void
    {
        $record = $this->record(['license_verified_at' => self::NOW]);

        self::assertSame(self::NOW + PackagePolicy::LEASE_SECONDS, PackagePolicy::recheckDueAt($record));
        self::assertSame(
            self::NOW + PackagePolicy::LEASE_SECONDS + PackagePolicy::GRACE_SECONDS,
            PackagePolicy::graceEndsAt($record),
        );
    }

    /**
     * The signed fields win outright, so the day the vendor starts emitting
     * them this stops being a local guess and becomes vendor policy — with no
     * further client change.
     */
    public function testTheSignedLeaseFieldsWinOverTheFallback(): void
    {
        $record = $this->record([
            'license_verified_at' => self::NOW,
            'license_refresh_required_at' => self::NOW + 600,
            'license_grace_until' => self::NOW + 1200,
        ]);

        self::assertSame(self::NOW + 600, PackagePolicy::recheckDueAt($record));
        self::assertSame(self::NOW + 1200, PackagePolicy::graceEndsAt($record));
    }

    /**
     * An unknown signed field must not break verification: the signature covers
     * the whole document, so a server that adds one stays compatible.
     */
    public function testAnUnknownSignedFieldDoesNotBreakVerification(): void
    {
        $package = $this->package(['license_something_new' => 'ignored']);

        $result = $this->acceptance($this->inventory())->accept(
            $package['payload'],
            $package['envelope'],
            'example.com',
            null,
            self::NOW,
        );

        self::assertTrue($result->isAccepted());
    }

    // ------------------------------------------------------- fail-closed state

    /** Before the cutoff, a verified licence works normally. */
    public function testEntitlementSurvivesUpToTheGraceCutoff(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();
        $this->write($store, $this->package(['license_verified_at' => self::NOW]));

        $lastGoodMoment = self::NOW + PackagePolicy::LEASE_SECONDS + PackagePolicy::GRACE_SECONDS;

        self::assertTrue($this->evaluator($store, $inventory)->evaluate($lastGoodMoment)->licensed);
    }

    /**
     * Past it, protected entitlement fails closed. Reaching this point means
     * every re-check failed for the whole lease AND the whole grace period,
     * because each success restamps license_verified_at.
     */
    public function testStaleEntitlementFailsClosedPastTheGraceCutoff(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();
        $this->write($store, $this->package(['license_verified_at' => self::NOW]));

        $tooLate = self::NOW + PackagePolicy::LEASE_SECONDS + PackagePolicy::GRACE_SECONDS + 1;
        $state = $this->evaluator($store, $inventory)->evaluate($tooLate);

        self::assertFalse($state->licensed);
        self::assertSame(EntitlementState::LEASE_EXPIRED, $state->status);
    }

    /**
     * A lapsed lease is NOT a withdrawal, and the distinction is load-bearing:
     * the unattended re-check stops pulling once withdrawn, so routing a lease
     * lapse there would strand a perfectly valid licence for ever behind
     * nothing more than a spell of unreachability.
     *
     * Withdrawn means "something authoritative said you lost it, stop asking".
     * A lapsed lease means "cannot confirm, keep asking".
     */
    public function testALapsedLeaseDoesNotStopTheReCheck(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->write($store, $this->package(['license_verified_at' => self::NOW]));

        $tooLate = self::NOW + PackagePolicy::LEASE_SECONDS + PackagePolicy::GRACE_SECONDS + 1;

        self::assertSame(ProvisioningWorkflow::OK, $this->workflow($store)->refreshIfDue($tooLate));
    }

    /** A lifetime licence has no expiry, but it is not exempt from the lease. */
    public function testALifetimeLicenceIsStillSubjectToTheLease(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();
        $this->write($store, $this->package([
            'license_lifetime' => true,
            'license_expires_at' => null,
            'license_verified_at' => self::NOW,
        ]));

        $tooLate = self::NOW + PackagePolicy::LEASE_SECONDS + PackagePolicy::GRACE_SECONDS + 1;

        self::assertSame(
            EntitlementState::LEASE_EXPIRED,
            $this->evaluator($store, $inventory)->evaluate($tooLate)->status,
        );
    }

    // -------------------------------------------------- the unattended pull

    /** Nothing to renew before the deadline. */
    public function testTheReCheckDoesNothingBeforeTheDeadline(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->write($store, $this->package(['license_verified_at' => self::NOW]));

        self::assertSame(ProvisioningWorkflow::NOT_DUE, $this->workflow($store)->refreshIfDue(self::NOW + 60));
    }

    /**
     * THE RE-BIND HOLE, and it bit three of the sibling packages.
     *
     * The vendor's /api/v1/verify binds the domain it is asked about on a
     * REFRESH, not only on an activation. A released host whose cron kept
     * polling would therefore re-bind itself and silently re-license, undoing
     * the very revocation it had already accepted. Only a vendor push or an
     * administrator pressing "Update licence" may bring it back.
     */
    public function testTheReCheckRefusesToPullOnceWithdrawn(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->write($store, $this->revocation('example.com', ['newsite.com'], [
            'license_verified_at' => self::NOW,
            'license_version' => 8,
        ]));

        $due = self::NOW + PackagePolicy::LEASE_SECONDS + 1;

        self::assertSame(ProvisioningWorkflow::WITHDRAWN, $this->workflow($store)->refreshIfDue($due));
    }

    /**
     * The same hole reached through a restored backup: the file on disk
     * verifies, but the rollback barrier has moved past it, so it is a
     * withdrawn state wearing an older record's clothes. Pulling would
     * re-bind and re-license.
     */
    public function testTheReCheckRefusesToPullOnARestoredBackup(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $store->raise('SS-PRO-0001-ABCD', 8);
        $this->write($store, $this->package(['license_version' => 7, 'license_verified_at' => self::NOW]), false);

        $due = self::NOW + PackagePolicy::LEASE_SECONDS + 1;

        self::assertSame(ProvisioningWorkflow::WITHDRAWN, $this->workflow($store)->refreshIfDue($due));
    }

    /**
     * When no configured host is authorised any more, asking the vendor about
     * whatever host this installation now serves would make re-binding an
     * unattended decision. That is an administrator's call.
     */
    public function testTheReCheckRefusesWhenNoConfiguredHostIsAuthorised(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->write($store, $this->package([
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
            'license_verified_at' => self::NOW,
        ]));

        $due = self::NOW + PackagePolicy::LEASE_SECONDS + 1;
        $elsewhere = $this->inventory(['moved-on.com'], 'moved-on.com');

        self::assertSame(
            ProvisioningWorkflow::NO_HOST_MATCH,
            $this->workflow($store, $elsewhere)->refreshIfDue($due),
        );
    }

    /** An unreachable vendor must not turn an hourly cron into a retry storm. */
    public function testAttemptsAreThrottled(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->write($store, $this->package(['license_verified_at' => self::NOW]));

        $due = self::NOW + PackagePolicy::LEASE_SECONDS + 1;

        self::assertSame(ProvisioningWorkflow::OK, $this->workflow($store)->refreshIfDue($due));
        self::assertSame(ProvisioningWorkflow::NOT_DUE, $this->workflow($store)->refreshIfDue($due + 60));
    }

    /**
     * The throttle marker is an ordinary file in var/, so the site owner can
     * write it. A FUTURE value therefore counts as "never attempted" — freezing
     * or forward-dating it can only bring the re-check forward, never switch it
     * off.
     */
    public function testAForwardDatedThrottleMarkerCountsAsNeverAttempted(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->write($store, $this->package(['license_verified_at' => self::NOW]));

        $due = self::NOW + PackagePolicy::LEASE_SECONDS + 1;
        $store->markAttempt($due + 86400 * 365);

        self::assertSame(ProvisioningWorkflow::OK, $this->workflow($store)->refreshIfDue($due));
    }

    /** A wiped var/ must self-heal rather than silently stop re-checking. */
    public function testAMissingRecordIsReportedRatherThanTreatedAsUpToDate(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        self::assertSame(ProvisioningWorkflow::NOTHING_STORED, $this->workflow($store)->refreshIfDue(self::NOW));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param array<string, mixed> $overrides
     */
    private function record(array $overrides): ProvisioningRecord
    {
        $package = $this->package($overrides);
        $envelope = json_decode((string) json_encode($package['envelope']), true);
        \assert(\is_array($envelope));

        /** @var array<string, mixed> $envelope */
        $record = ProvisioningRecord::parse($package['bytes'], $envelope);
        \assert($record instanceof ProvisioningRecord);

        return $record;
    }

    /**
     * @param array{payload: string, envelope: \stdClass, bytes: string, document: array<string, mixed>} $package
     */
    private function write(ProvisioningStore $store, array $package, bool $raiseBarrier = true): void
    {
        $envelope = json_decode((string) json_encode($package['envelope']), true);
        \assert(\is_array($envelope));

        /** @var array<string, mixed> $envelope */
        self::assertTrue($store->activate($package['bytes'], $envelope, static fn (): bool => true));

        if ($raiseBarrier) {
            $store->raise((string) $package['document']['license_key'], (int) $package['document']['license_version']);
        }
    }

    private function acceptance(HostInventory $inventory, ?ProvisioningStore $store = null): PackageAcceptance
    {
        return new PackageAcceptance(
            $this->testVerifier(),
            $this->testRing(),
            $inventory,
            $store ?? new ProvisioningStore($this->projectDir),
        );
    }

    private function evaluator(ProvisioningStore $store, HostInventory $inventory): EntitlementEvaluator
    {
        return new EntitlementEvaluator($store, $this->acceptance($inventory, $store), $inventory);
    }

    /**
     * A workflow whose vendor always answers with a freshly verified package,
     * so a successful pull is observable. No real endpoint is contacted.
     */
    private function workflow(ProvisioningStore $store, ?HostInventory $inventory = null): ProvisioningWorkflow
    {
        $inventory ??= $this->inventory();
        $acceptance = $this->acceptance($inventory, $store);
        $log = new OperationLog(new NullLogger());

        // What the vendor hands back on a successful renewal: a higher version
        // and a restamped license_verified_at, which is what moves both lease
        // deadlines forward.
        $renewed = $this->package([
            'license_version' => 99,
            'license_verified_at' => self::NOW + PackagePolicy::LEASE_SECONDS + PackagePolicy::GRACE_SECONDS + 1,
        ]);

        // server_time is echoed from the request's own timestamp: the client
        // refuses an answer more than 15 minutes out of step, and these tests
        // deliberately run the clock weeks forward.
        $client = new MockHttpClient(
            function (string $method, string $url, array $options) use ($renewed): MockResponse {
                /** @var array<string, mixed> $sent */
                $sent = json_decode((string) $options['body'], true);

                return new MockResponse(
                    (string) json_encode([
                        'status' => 'valid',
                        'request_id' => (string) $sent['request_id'],
                        'server_time' => (int) $sent['timestamp'],
                        'license_payload_b64' => $renewed['payload'],
                        'integrity' => $renewed['envelope'],
                    ]),
                    ['response_headers' => ['content-type' => 'application/json']],
                );
            },
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
