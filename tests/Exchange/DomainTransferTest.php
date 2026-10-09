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
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\EntitlementState;
use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * Moving a licence from one installation to another.
 *
 * THE HOLE THESE TESTS EXIST FOR. A signed licence is verified offline, which
 * is the point — but it also means an installation keeps working on the payload
 * already on its disk until something newer arrives. Move a licence from site A
 * to site B and both run licensed: nothing about A's licence expired, it was
 * MOVED. A only found out if somebody pressed "Update licence" there, which is
 * the one thing a person exploiting the gap will never do.
 *
 * The vendor therefore pushes a signed `revoked` payload to the host that lost
 * the licence. Before this work every one of the checks below refused that
 * payload — each with a plausible-looking generic error, and each failing OPEN,
 * because a refused withdrawal leaves the old licence in place.
 */
final class DomainTransferTest extends TestCase
{
    use PackageFixture;

    private const NOW = 1784900000;

    private string $projectDir = '';

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-transfer-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);
    }

    protected function tearDown(): void
    {
        if ($this->projectDir !== '' && is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    // ---------------------------------------------------------------- intake

    /**
     * The headline case, and the one the whole feature turns on: a
     * cryptographically authentic negative entitlement is a successful state
     * UPDATE, not a verification failure.
     */
    public function testASignedRevocationIsAccepted(): void
    {
        $result = $this->accept($this->revocation('example.com', ['newsite.com']));

        self::assertTrue($result->isAccepted(), 'a signed revocation must be accepted, not refused as invalid');
        self::assertSame(ProvisioningRecord::STATUS_REVOKED, $result->record?->validationStatus());
    }

    /**
     * The target host is absent from the signed set BY CONSTRUCTION — that is
     * what "this host lost the licence" means. Requiring membership refused the
     * exact packet a domain transfer exists to send.
     */
    public function testTheRevokedHostIsAllowedToBeMissingFromTheSignedSet(): void
    {
        $package = $this->revocation('example.com', ['newsite.com']);

        self::assertNotContains('example.com', $package['document']['license_domains']);
        self::assertTrue($this->accept($package)->isAccepted());
    }

    /** The customer released their last domain: nothing remains authorised. */
    public function testAnEmptySignedSetIsAcceptedForARevocation(): void
    {
        self::assertTrue($this->accept($this->revocation('example.com', []))->isAccepted());
    }

    /**
     * The other half of that rule. An empty set on a GRANT authorises nothing
     * and is a fault, so the relaxation must not leak across.
     */
    public function testAnEmptySignedSetIsStillRefusedForAGrant(): void
    {
        $result = $this->accept($this->package(['license_domains' => []]));

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::HOST_SET_INVALID, $result->category);
    }

    /**
     * The vendor probes a released slot at BOTH the apex and its "www." host
     * and stops at the first acknowledgement. An installation that acknowledged
     * a withdrawal for a host it does not serve would end the transfer, and the
     * host that really needed telling would never be told — the revocation
     * looks delivered while the old site stays licensed.
     */
    public function testARevocationForAHostWeDoNotServeIsRefused(): void
    {
        $result = $this->accept(
            $this->revocation('example.com', ['newsite.com']),
            $this->inventory(['www.example.com'], 'www.example.com'),
        );

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::TARGET_NOT_SERVED, $result->category);
    }

    /**
     * ...and the same probe against the host it DOES serve is accepted, which
     * is what makes trying both worth the vendor's while.
     */
    public function testTheProbeForTheHostWeDoServeIsAccepted(): void
    {
        $result = $this->accept(
            $this->revocation('www.example.com', ['newsite.com']),
            $this->inventory(['www.example.com'], 'www.example.com'),
        );

        self::assertTrue($result->isAccepted());
    }

    /**
     * A status this release does not know is refused outright rather than
     * guessed at. Guessing "entitled" would grant on a state we cannot read;
     * guessing "withdrawn" would darken a paying customer's site over a field
     * a future server merely added.
     */
    public function testAnUnknownValidationStatusIsRefused(): void
    {
        $result = $this->accept($this->package(['validation_status' => 'suspended']));

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::STATUS, $result->category);
    }

    /**
     * Every extra condition on a negative state is another way to refuse a
     * genuine withdrawal, and a refused withdrawal fails OPEN. So the tier
     * allowlist — which is real policy for a grant — must not gate one.
     */
    public function testTheTierAllowlistDoesNotGateAWithdrawal(): void
    {
        $withdrawal = $this->revocation('example.com', ['newsite.com'], ['license_package' => 'free']);
        self::assertTrue($this->accept($withdrawal)->isAccepted());

        // Still enforced where it belongs.
        $grant = $this->package(['license_package' => 'free']);
        self::assertSame(PackageAcceptance::PACKAGE, $this->accept($grant)->category);
    }

    /**
     * Same reasoning for the term. A licence whose dates have drifted into a
     * shape the grant path rejects must still be withdrawable, or it could
     * never be taken back at all.
     */
    public function testTermConsistencyDoesNotGateAWithdrawal(): void
    {
        $contradictory = ['license_lifetime' => true, 'license_expires_at' => 1815536000];

        self::assertTrue($this->accept($this->revocation('example.com', ['newsite.com'], $contradictory))->isAccepted());
        self::assertSame(PackageAcceptance::DATES, $this->accept($this->package($contradictory))->category);
    }

    /**
     * A withdrawal is not exempt from ADDRESSING. A packet naming somebody
     * else's installation is never ours to apply, however genuinely signed.
     */
    public function testAWithdrawalAddressedElsewhereIsStillRefused(): void
    {
        $package = $this->revocation('someone-else.com', ['newsite.com']);
        $result = $this->acceptance($this->inventory())->accept(
            $package['payload'],
            $package['envelope'],
            'example.com',
            null,
            self::NOW,
            true,
        );

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::DOMAIN_MISMATCH, $result->category);
    }

    // ------------------------------------------------------- rollback barrier

    /**
     * record.json and record.seal are exactly what an administrator has in a
     * backup, and restoring them restores a genuinely signed, still perfectly
     * verifying licence. Without a barrier that is all it takes to undo a
     * revocation.
     */
    public function testRestoringAPreTransferBackupCannotWinTheLicenceBack(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $valid = $this->package(['license_version' => 7]);

        // The state as it was before the transfer, and the barrier it raised.
        $this->store($store, $valid);
        self::assertTrue($this->acceptance($this->inventory(), $store)->checkStored($this->loaded($store), self::NOW)->isAccepted());

        // The transfer arrives.
        $this->store($store, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]));

        // The administrator restores yesterday's files.
        $this->store($store, $valid, false);

        $verdict = $this->acceptance($this->inventory(), $store)->checkStored($this->loaded($store), self::NOW);

        self::assertFalse($verdict->isAccepted(), 'a restored pre-transfer record must not verify again');
        self::assertSame(PackageAcceptance::SUPERSEDED, $verdict->category);
    }

    /**
     * "Remove licence" must not become the supported route around a
     * revocation. The barrier therefore survives removal — it grants nothing on
     * its own, so keeping it cannot keep the product switched on.
     */
    public function testRemoveThenRestoreStillCannotWinTheLicenceBack(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $valid = $this->package(['license_version' => 7]);

        $this->store($store, $valid);
        $this->store($store, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]));

        $store->remove();
        self::assertNull($store->load());

        $this->store($store, $valid, false);

        self::assertSame(
            PackageAcceptance::SUPERSEDED,
            $this->acceptance($this->inventory(), $store)->checkStored($this->loaded($store), self::NOW)->category,
        );
    }

    /**
     * The barrier is per LICENCE KEY, and that is not a detail.
     *
     * Version numbers count within one licence, so a replacement licence bought
     * after a withdrawal legitimately starts at version 1 while the withdrawn
     * one had reached 8. A single global watermark would refuse exactly the
     * customers who did the right thing and bought again.
     */
    public function testAReplacementLicenceActivatesDespiteTheOldKeysBarrier(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        $this->store($store, $this->package(['license_version' => 7]));
        $this->store($store, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]));
        self::assertSame(8, $store->floor('SS-PRO-0001-ABCD'));

        $replacement = $this->package(['license_key' => 'SS-PRO-NEW-0002', 'license_version' => 1]);

        self::assertSame(0, $store->floor('SS-PRO-NEW-0002'));
        self::assertTrue($this->accept($replacement, $this->inventory(), $store)->isAccepted());
    }

    /**
     * The vendor settles a transfer at ONE revision and states it from both
     * sides: the released host is sent `revoked` at N, every surviving host is
     * sent `valid` at N. An installation serving two of the licence's hosts
     * receives both, so insisting on a strictly higher version would refuse the
     * second packet in exactly the case it exists for.
     */
    public function testTheSurvivingHostsReissueIsAcceptedAtTheSameVersion(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory(['a.example.com', 'b.example.com'], 'a.example.com');

        $this->store($store, $this->package([
            'license_domain' => 'a.example.com',
            'license_domains' => ['a.example.com', 'b.example.com'],
            'license_version' => 7,
        ]), true, $inventory);

        // "a" is released; the licence keeps "b". Both packets carry version 8.
        $this->store($store, $this->revocation('a.example.com', ['b.example.com'], ['license_version' => 8]), true, $inventory);

        $reissue = $this->package([
            'license_domain' => 'b.example.com',
            'license_domains' => ['b.example.com'],
            'license_version' => 8,
        ]);

        $result = $this->acceptance($inventory, $store)->accept(
            $reissue['payload'],
            $reissue['envelope'],
            'b.example.com',
            $store->load(),
            self::NOW,
            true,
        );

        self::assertTrue($result->isAccepted(), 'the kept host must still get its re-issue at the transfer revision');
    }

    /**
     * The rule the equal-version allowance must NOT swallow: a push that
     * re-states a grant at a version already held changes nothing and is
     * refused, so an old captured packet cannot be replayed over a newer state.
     */
    public function testAGrantIsNotReappliedOverAGrantAtTheSameVersion(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $this->store($store, $this->package(['license_version' => 7]));

        $result = $this->acceptance($this->inventory(), $store)->accept(
            $this->package(['license_version' => 7])['payload'],
            $this->package(['license_version' => 7])['envelope'],
            'example.com',
            $store->load(),
            self::NOW,
            true,
        );

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::ROLLBACK, $result->category);
    }

    // ------------------------------------------------------------ entitlement

    /**
     * The outcome the customer's competitor cares about: after the transfer,
     * the old installation is off.
     */
    public function testTheReleasedInstallationBecomesUnlicensed(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();

        $this->store($store, $this->package(['license_version' => 7]), true, $inventory);
        self::assertTrue($this->evaluator($store, $inventory)->evaluate(self::NOW)->licensed);

        $this->store($store, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]), true, $inventory);

        $state = $this->evaluator($store, $inventory)->evaluate(self::NOW);

        self::assertFalse($state->licensed);
        self::assertSame(EntitlementState::REVOKED, $state->status);
    }

    /**
     * And the outcome an ordinary customer cares about: releasing ONE of their
     * domains must not take the whole installation down.
     *
     * This is why the evaluator has no "revoked" short-circuit. The withdrawal
     * carries the licence's current authorised set, so the ordinary
     * intersection answers both cases for free.
     */
    public function testReleasingOneOfSeveralHostsKeepsTheRestRunning(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory(['a.example.com', 'b.example.com'], 'b.example.com');

        $this->store($store, $this->revocation('a.example.com', ['b.example.com'], [
            'license_version' => 8,
        ]), true, $inventory);

        $state = $this->evaluator($store, $inventory)->evaluate(self::NOW);

        self::assertTrue($state->licensed, 'a host the licence kept must go on working');
        self::assertSame('b.example.com', $state->matchedHost);
    }

    /**
     * The two packets are delivered independently and may arrive in either
     * order. Because the record left on disk always states the correct host
     * set, the answer does not depend on which one landed last.
     */
    public function testTheAnswerDoesNotDependOnWhichPacketArrivesLast(): void
    {
        $inventory = $this->inventory();

        $withdrawalLast = new ProvisioningStore($this->projectDir . '/one');
        $this->store($withdrawalLast, $this->package(['license_version' => 7]), true, $inventory);
        $this->store($withdrawalLast, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]), true, $inventory);

        $withdrawalOnly = new ProvisioningStore($this->projectDir . '/two');
        $this->store($withdrawalOnly, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]), true, $inventory);

        self::assertFalse($this->evaluator($withdrawalLast, $inventory)->evaluate(self::NOW)->licensed);
        self::assertFalse($this->evaluator($withdrawalOnly, $inventory)->evaluate(self::NOW)->licensed);
    }

    /**
     * A signed `expired` is licence-wide, unlike a revocation. It does not get
     * to be rescued by the host set still naming this installation.
     */
    public function testASignedExpiredStateIsLicenceWideEvenWhenTheHostStillMatches(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();

        $this->store($store, $this->package([
            'validation_status' => 'expired',
            'license_domains' => ['example.com'],
            'license_lifetime' => true,
            'license_expires_at' => null,
            'license_version' => 9,
        ]), true, $inventory);

        $state = $this->evaluator($store, $inventory)->evaluate(self::NOW);

        self::assertFalse($state->licensed);
        self::assertSame(EntitlementState::EXPIRED, $state->status);
    }

    /**
     * A withdrawal has to keep verifying on every read, or the panel would tell
     * the administrator their files are corrupt instead of that their licence
     * has moved.
     */
    public function testAStoredWithdrawalStaysReadableRatherThanBecomingUnverifiable(): void
    {
        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();

        $this->store($store, $this->revocation('example.com', ['newsite.com'], ['license_version' => 8]), true, $inventory);

        $state = $this->evaluator($store, $inventory)->evaluate(self::NOW);

        self::assertNotSame(EntitlementState::UNVERIFIABLE, $state->status);
        self::assertSame(['newsite.com'], $state->signedHosts, 'the panel can say where the licence went');
    }

    /**
     * Tidying the released host out of the site configuration is an ordinary,
     * legitimate thing for a customer to do after a transfer. It must not cost
     * them the hosts the licence KEPT.
     *
     * This is why the addressing rule is asked only of an arriving packet:
     * re-asking it on every read made the stored withdrawal unreadable once its
     * target was no longer configured, and an unreadable record is an
     * unlicensed installation.
     */
    public function testDroppingTheReleasedHostFromTheSiteConfigKeepsTheKeptHostsRunning(): void
    {
        $store = new ProvisioningStore($this->projectDir);

        // The install served both hosts when the withdrawal for "a" arrived.
        $atIntake = $this->inventory(['a.example.com', 'b.example.com'], 'a.example.com');
        $withdrawal = $this->revocation('a.example.com', ['b.example.com'], ['license_version' => 8]);

        self::assertTrue($this->accept($withdrawal, $atIntake, $store)->isAccepted());
        $this->store($store, $withdrawal, true, $atIntake);

        // Later the administrator removes the released host from tl_page.dns.
        $tidied = $this->inventory(['b.example.com'], 'b.example.com');
        $state = $this->evaluator($store, $tidied)->evaluate(self::NOW);

        self::assertTrue($state->licensed, 'the host the licence kept must still be licensed');
        self::assertSame('b.example.com', $state->matchedHost);
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param array{payload: string, envelope: \stdClass, bytes: string, document: array<string, mixed>} $package
     */
    private function accept(array $package, ?HostInventory $inventory = null, ?ProvisioningStore $store = null): \VTinnovations\SeoStudio\Exchange\AcceptanceResult
    {
        $inventory ??= $this->inventory();

        return $this->acceptance($inventory, $store)->accept(
            $package['payload'],
            $package['envelope'],
            (string) $package['document']['license_domain'],
            null,
            self::NOW,
            true,
        );
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
     * Writes a package as the active state.
     *
     * $raiseBarrier is false only where a test is standing in for an
     * administrator restoring files from a backup — that route writes bytes
     * without going through the workflow, so it cannot raise anything.
     *
     * @param array{payload: string, envelope: \stdClass, bytes: string, document: array<string, mixed>} $package
     */
    private function store(
        ProvisioningStore $store,
        array $package,
        bool $raiseBarrier = true,
        ?HostInventory $inventory = null,
    ): void {
        $envelope = json_decode((string) json_encode($package['envelope']), true);
        \assert(\is_array($envelope));

        /** @var array<string, mixed> $envelope */
        $written = $store->activate($package['bytes'], $envelope, static fn (): bool => true);
        self::assertTrue($written);

        unset($inventory);

        if ($raiseBarrier) {
            $store->raise((string) $package['document']['license_key'], (int) $package['document']['license_version']);
        }
    }

    private function loaded(ProvisioningStore $store): ProvisioningRecord
    {
        $record = $store->load();
        \assert($record instanceof ProvisioningRecord);

        return $record;
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
