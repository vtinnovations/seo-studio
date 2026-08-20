<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests;

use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Core\Content\HostName;
use VTinnovations\SeoStudio\Core\Security\CanonicalForm;
use VTinnovations\SeoStudio\Core\Security\SignatureVerifier;
use VTinnovations\SeoStudio\Core\Security\TrustAnchor;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;

/**
 * Signed test packages.
 *
 * A deterministic Ed25519 seed keeps every signature reproducible, so these are
 * fixed vectors rather than random data. The keypair is test-only: it is never
 * the vendor's key and never ships.
 */
trait PackageFixture
{
    private static ?string $secretKey = null;

    private static ?string $publicKey = null;

    protected function testRing(): TrustAnchors
    {
        $this->initKeys();

        return new TrustAnchors([
            new TrustAnchor('test-key', 'ed25519', (string) self::$publicKey, [
                TrustAnchor::PURPOSE_DOCUMENT,
                TrustAnchor::PURPOSE_ENVELOPE,
                TrustAnchor::PURPOSE_REQUEST,
            ], 0, null),
        ]);
    }

    protected function testVerifier(): SignatureVerifier
    {
        return new SignatureVerifier($this->testRing());
    }

    protected function sign(string $message): string
    {
        $this->initKeys();

        return base64_encode(sodium_crypto_sign_detached($message, (string) self::$secretKey));
    }

    /**
     * A complete signed package.
     *
     * @param array<string, mixed> $overrides         document members to replace
     * @param array<string, mixed> $envelopeOverrides envelope members to replace,
     *                                                applied before the envelope
     *                                                is signed so the result is
     *                                                authentic, not tampered
     *
     * @return array{payload: string, envelope: \stdClass, bytes: string, document: array<string, mixed>}
     */
    protected function package(array $overrides = [], array $envelopeOverrides = []): array
    {
        $document = array_merge([
            'schema_version' => 2,
            'project' => PackagePolicy::PROJECT,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'license_key' => 'SS-PRO-0001-ABCD',
            'license_domain' => 'example.com',
            'license_domains' => ['example.com'],
            'license_max_domains' => 9999,
            'license_package' => 'pro',
            'license_features' => [],
            'license_version' => 7,
            'license_issued_at' => 1784000000,
            'license_starts_at' => 1784000000,
            'license_expires_at' => 1815536000,
            'license_lifetime' => false,
            'license_verified_at' => 1784880547,
            'free_available' => false,
            'validation_status' => 'valid',
        ], $overrides);

        $unsigned = CanonicalForm::decode((string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $document['signature'] = $this->sign(CanonicalForm::encode($unsigned));

        $bytes = (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $envelope = array_merge([
            'project' => PackagePolicy::PROJECT,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'license_version' => $document['license_version'],
            'license_md5' => md5($bytes),
            'generated_at' => 1784880547,
            'key_id' => 'test-key',
            'signature_algorithm' => 'ed25519',
        ], $envelopeOverrides);

        $envelope['signature'] = $this->sign(CanonicalForm::encode(CanonicalForm::decode((string) json_encode($envelope))));

        $sealed = CanonicalForm::decode((string) json_encode($envelope));
        \assert($sealed instanceof \stdClass);

        return [
            'payload' => base64_encode($bytes),
            'envelope' => $sealed,
            'bytes' => $bytes,
            'document' => $document,
        ];
    }

    /**
     * @param list<string> $hosts
     */
    protected function inventory(array $hosts = ['example.com'], ?string $current = 'example.com'): HostInventory
    {
        return new class($hosts, $current) implements HostInventory {
            /** @param list<string> $hosts */
            public function __construct(private array $hosts, private ?string $current)
            {
            }

            public function configuredHosts(): array
            {
                return HostName::canonicalSet($this->hosts);
            }

            public function intersect(array $signedHosts): array
            {
                return array_values(array_intersect($signedHosts, $this->configuredHosts()));
            }

            public function matchedHost(array $signedHosts): ?string
            {
                $intersection = $this->intersect($signedHosts);

                if ($intersection === []) {
                    return null;
                }

                if ($this->current !== null && \in_array($this->current, $intersection, true)) {
                    return $this->current;
                }

                return $intersection[0];
            }

            public function outboundHost(): ?string
            {
                return $this->current ?? ($this->configuredHosts()[0] ?? null);
            }

            public function reset(): void
            {
            }
        };
    }

    private function initKeys(): void
    {
        if (self::$secretKey !== null) {
            return;
        }

        $pair = sodium_crypto_sign_seed_keypair(str_repeat("\x2a", \SODIUM_CRYPTO_SIGN_SEEDBYTES));
        self::$secretKey = sodium_crypto_sign_secretkey($pair);
        self::$publicKey = sodium_crypto_sign_publickey($pair);
    }
}
