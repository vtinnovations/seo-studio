<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Core;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VTinnovations\SeoStudio\Core\Config\PackageLabel;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * The package value in the licence section is exactly one of Free / Pro /
 * Demo, decided by the license_is_demo flag first and the package string
 * second — and by nothing else. Display only: entitlement is untouched.
 */
final class PackageLabelTest extends TestCase
{
    use PackageFixture;

    /** @var array<mixed>|null */
    private ?array $savedLang = null;

    protected function setUp(): void
    {
        // Real English strings, so the tests see what an administrator sees.
        $this->savedLang = $GLOBALS['TL_LANG'] ?? null;
        unset($GLOBALS['TL_LANG']);
        require __DIR__ . '/../../contao/languages/en/default.php';
    }

    protected function tearDown(): void
    {
        if ($this->savedLang === null) {
            unset($GLOBALS['TL_LANG']);
        } else {
            $GLOBALS['TL_LANG'] = $this->savedLang;
        }
    }

    // ------------------------------------------------- resolution order

    public function testDemoFlagWithProPackageIsDemo(): void
    {
        $record = $this->record(['license_package' => 'pro', 'license_is_demo' => true]);

        self::assertTrue($record->isDemo());
        self::assertSame(PackageLabel::DEMO, PackageLabel::resolve($record->package(), $record->isDemo()));
        self::assertSame('Demo', PackageLabel::text($record->package(), $record->isDemo()));
    }

    public function testProWithoutFlagIsPro(): void
    {
        $record = $this->record(['license_package' => 'pro']);

        self::assertFalse($record->isDemo());
        self::assertSame('Pro', PackageLabel::text($record->package(), $record->isDemo()));
    }

    public function testFreeWithoutFlagIsFree(): void
    {
        self::assertSame('Free', PackageLabel::text('free', false));
    }

    public function testExplicitDemoPackageStringIsDemo(): void
    {
        self::assertSame('Demo', PackageLabel::text('demo', false));
    }

    /**
     * A lapsed paid licence re-issued as the free tier: the server drops the
     * flag. Its expiry, free_available and lifetime fields must not turn it
     * into a demo.
     */
    public function testFreeFallbackIsFreeNotDemo(): void
    {
        $record = $this->record([
            'license_package' => 'free',
            'free_available' => true,
            'license_expires_at' => 1784100000, // short, recently lapsed term
        ]);

        self::assertFalse($record->isDemo());
        self::assertSame('Free', PackageLabel::text($record->package(), $record->isDemo()));
    }

    /** The term is not the package: lifetime Pro reads "Pro", never "Lifetime". */
    public function testLifetimeProIsPro(): void
    {
        $record = $this->record(['license_package' => 'pro', 'license_lifetime' => true, 'license_expires_at' => null]);

        self::assertFalse($record->isDemo());
        self::assertSame('Pro', PackageLabel::text($record->package(), $record->isDemo()));
    }

    // ------------------------------------------- non-boolean / absent flag

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notTrueFlags(): iterable
    {
        yield 'false' => [false];
        yield 'null' => [null];
        yield 'string yes' => ['yes'];
        yield 'string true' => ['true'];
        yield 'integer 1' => [1];
        yield 'array' => [[true]];
    }

    #[DataProvider('notTrueFlags')]
    public function testAnythingButTrueFallsBackToThePackageString(mixed $flag): void
    {
        $record = $this->record(['license_package' => 'pro', 'license_is_demo' => $flag]);

        self::assertFalse($record->isDemo(), 'only a literal true marks a demo');
        self::assertSame('Pro', PackageLabel::text($record->package(), $record->isDemo()));

        // The resolver applies the same rule to a raw value handed to it.
        self::assertSame(PackageLabel::PRO, PackageLabel::resolve('pro', $flag));
        self::assertSame(PackageLabel::FREE, PackageLabel::resolve('free', $flag));
    }

    /** Payloads issued before the field existed parse and behave as before. */
    public function testPayloadWithoutTheKeyBehavesAsBefore(): void
    {
        $package = $this->package(['license_package' => 'pro']);
        self::assertArrayNotHasKey('license_is_demo', $package['document']);

        $record = $this->record(['license_package' => 'pro']);

        self::assertSame('pro', $record->package());
        self::assertFalse($record->isDemo());
        self::assertSame(PackageLabel::PRO, PackageLabel::resolve($record->package()));
    }

    /** Carrying the flag never changes whether the payload is accepted in shape. */
    public function testPayloadCarryingTheFlagStillParses(): void
    {
        self::assertInstanceOf(ProvisioningRecord::class, $this->record(['license_is_demo' => true]));
        self::assertInstanceOf(ProvisioningRecord::class, $this->record(['license_is_demo' => 'yes']));
    }

    // ------------------------------------------------- display details

    public function testValueIsNotUpperCased(): void
    {
        self::assertSame('Pro', PackageLabel::text('pro'));
        self::assertNotSame('PRO', PackageLabel::text('pro'));
    }

    public function testUnknownPackageStringIsShownAsSent(): void
    {
        self::assertNull(PackageLabel::resolve('enterprise'));
        self::assertSame('enterprise', PackageLabel::text('enterprise'));
    }

    /** Display only: the demo flag must not widen what is accepted. */
    public function testAcceptedPackagesAreUnchanged(): void
    {
        self::assertSame(['pro'], PackagePolicy::ACCEPTED_PACKAGES);
        self::assertTrue(PackagePolicy::acceptsPackage('pro'));
        self::assertFalse(PackagePolicy::acceptsPackage('demo'));
        self::assertFalse(PackagePolicy::acceptsPackage('free'));
    }

    // ---------------------------------------------------------- helpers

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
        self::assertInstanceOf(ProvisioningRecord::class, $record);

        return $record;
    }
}
