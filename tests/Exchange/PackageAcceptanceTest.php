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
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * Product identity in the acceptance pipeline.
 *
 * The regression these tests pin down: the vendor catalogue spells the product
 * title "SEO Studio" while the wire protocol sends the compact "SeoStudio".
 * When the title was compared byte-for-byte, every genuinely signed licence was
 * rejected as "product_mismatch" and no site could ever activate.
 *
 * Titles are therefore compared on their letters and digits, while the slug —
 * which is what actually separates one product from another — stays exact.
 */
final class PackageAcceptanceTest extends TestCase
{
    use PackageFixture;

    private const NOW = 1784880600;

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function acceptedTitles(): array
    {
        return [
            'catalogue spelling, both fields' => ['SEO Studio', 'SEO Studio'],
            'compact document, spaced envelope' => ['SeoStudio', 'SEO Studio'],
            'spaced document, compact envelope' => ['SEO Studio', 'SeoStudio'],
            'hyphenated' => ['seo-studio', 'seo-studio'],
            'upper case' => ['SEO STUDIO', 'SEO STUDIO'],
        ];
    }

    /**
     * @dataProvider acceptedTitles
     */
    public function testEveryCatalogueSpellingOfTheTitleIsAccepted(string $documentTitle, string $envelopeTitle): void
    {
        $package = $this->package(['project' => $documentTitle], ['project' => $envelopeTitle]);

        $result = $this->acceptance()->accept(
            $package['payload'],
            $package['envelope'],
            'example.com',
            null,
            self::NOW,
        );

        self::assertTrue($result->isAccepted(), sprintf('rejected as "%s"', (string) $result->category));
    }

    public function testATitleNamingAnotherProductIsRejected(): void
    {
        $package = $this->package(['project' => 'FAQ Studio'], ['project' => 'FAQ Studio']);

        $result = $this->acceptance()->accept($package['payload'], $package['envelope'], 'example.com', null, self::NOW);

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::PRODUCT, $result->category);
    }

    public function testAnEmptyTitleIsRejected(): void
    {
        $package = $this->package(['project' => ' '], ['project' => ' ']);

        $result = $this->acceptance()->accept($package['payload'], $package['envelope'], 'example.com', null, self::NOW);

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::PRODUCT, $result->category);
    }

    /**
     * The slug is the machine identifier: no spelling latitude at all.
     */
    public function testADifferentSlugInTheDocumentIsRejected(): void
    {
        $package = $this->package(['project_slug' => 'faq-studio']);

        $result = $this->acceptance()->accept($package['payload'], $package['envelope'], 'example.com', null, self::NOW);

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::PRODUCT, $result->category);
    }

    public function testADifferentSlugInTheEnvelopeIsRejected(): void
    {
        $package = $this->package([], ['project_slug' => 'faq-studio']);

        $result = $this->acceptance()->accept($package['payload'], $package['envelope'], 'example.com', null, self::NOW);

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::ENVELOPE_MISMATCH, $result->category);
    }

    /**
     * The title latitude must not leak into the envelope/document binding.
     */
    public function testAnEnvelopeTitleNamingAnotherProductIsRejected(): void
    {
        $package = $this->package([], ['project' => 'Something Else']);

        $result = $this->acceptance()->accept($package['payload'], $package['envelope'], 'example.com', null, self::NOW);

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::ENVELOPE_MISMATCH, $result->category);
    }

    /**
     * The tamper tripwire still fires on the accepted spelling, so the relaxed
     * title comparison has not turned into a way in.
     */
    public function testTamperedPayloadBytesAreStillRejected(): void
    {
        $package = $this->package(['project' => 'SEO Studio'], ['project' => 'SEO Studio']);

        $tampered = base64_encode(str_replace(
            '"license_package":"pro"',
            '"license_package":"prO"',
            (string) base64_decode($package['payload'], true),
        ));

        $result = $this->acceptance()->accept($tampered, $package['envelope'], 'example.com', null, self::NOW);

        self::assertFalse($result->isAccepted());
        self::assertSame(PackageAcceptance::DIGEST_MISMATCH, $result->category);
    }

    private function acceptance(): PackageAcceptance
    {
        return new PackageAcceptance($this->testVerifier(), $this->testRing(), $this->inventory());
    }
}
