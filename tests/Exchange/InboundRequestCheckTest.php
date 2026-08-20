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
use Symfony\Component\HttpFoundation\Request;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\Exchange\InboundRequestCheck;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * Authentication of vendor-initiated updates: the "vt-one/request-sig-v1"
 * six-line message, the replay window and header/body agreement.
 */
final class InboundRequestCheckTest extends TestCase
{
    use PackageFixture;

    private const NOW = 1784880547;

    public function testValidSignedRequestIsAuthenticated(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $result = $check->authenticate($this->signedRequest(), self::NOW);

        self::assertTrue($result->authenticated, $result->category);
        self::assertSame('req-1', $result->requestId);
        self::assertSame('example.com', $result->domain);
        self::assertNotSame('', $result->bodyDigest);
        // The nonce is stored only as a digest.
        self::assertSame(hash('sha256', 'nonce-1'), $result->nonceDigest);
    }

    public function testUnsignedRequestIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());
        $request = $this->signedRequest();
        $request->headers->remove('X-VT-Signature');

        $result = $check->authenticate($request, self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame('authentication_incomplete', $result->category);
    }

    public function testTamperedBodyIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $original = $this->signedRequest();
        $tampered = Request::create(
            Endpoint::updaterPath(),
            'POST',
            [],
            [],
            [],
            $original->server->all(),
            (string) $original->getContent() . ' ',
        );
        $tampered->headers->replace($original->headers->all());

        $result = $check->authenticate($tampered, self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame('request_signature_invalid', $result->category);
    }

    public function testStaleTimestampIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $result = $check->authenticate($this->signedRequest(), self::NOW + 3600);

        self::assertFalse($result->authenticated);
        self::assertSame('timestamp_outside_window', $result->category);
    }

    public function testFutureTimestampIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $result = $check->authenticate($this->signedRequest(), self::NOW - 3600);

        self::assertFalse($result->authenticated);
        self::assertSame('timestamp_outside_window', $result->category);
    }

    public function testHeaderBodyMismatchIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        // Body claims a different nonce than the signed headers.
        $result = $check->authenticate($this->signedRequest(['nonce' => 'other-nonce']), self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame('metadata_mismatch', $result->category);
    }

    public function testAnotherProductsPushIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $result = $check->authenticate($this->signedRequest(['project' => 'SomethingElse']), self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame('product_mismatch', $result->category);
    }

    public function testUnknownKeyIdIsRefused(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());
        $request = $this->signedRequest();
        $request->headers->set('X-VT-Key-ID', 'rotated-away');

        $result = $check->authenticate($request, self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame(TrustAnchors::CATEGORY_UNKNOWN, $result->category);
    }

    public function testEmptyKeyRingFailsClosed(): void
    {
        $empty = new TrustAnchors([]);
        $check = new InboundRequestCheck(new \VTinnovations\SeoStudio\Core\Security\SignatureVerifier($empty), $empty);

        $result = $check->authenticate($this->signedRequest(), self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame(TrustAnchors::CATEGORY_EMPTY, $result->category);
    }

    public function testOversizedBodyIsRefusedBeforeParsing(): void
    {
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $request = $this->signedRequest([], str_repeat('x', InboundRequestCheck::MAX_BODY_BYTES + 1));

        $result = $check->authenticate($request, self::NOW);

        self::assertFalse($result->authenticated);
        self::assertSame('body_size', $result->category);
    }

    public function testKeyIdIsNotPartOfTheSignedMessage(): void
    {
        // Signing without the key id in the message must still verify, which is
        // what proves the header is a selector only.
        $check = new InboundRequestCheck($this->testVerifier(), $this->testRing());

        $result = $check->authenticate($this->signedRequest(), self::NOW);

        self::assertTrue($result->authenticated);
    }

    /**
     * @param array<string, mixed> $bodyOverrides
     */
    private function signedRequest(array $bodyOverrides = [], ?string $rawBody = null): Request
    {
        $package = $this->package();

        $body = array_merge([
            'action' => 'license_update',
            'project' => PackagePolicy::PROJECT,
            'project_slug' => PackagePolicy::PROJECT_SLUG,
            'product_id' => PackagePolicy::PRODUCT_ID,
            'domain' => 'example.com',
            'request_id' => 'req-1',
            'timestamp' => self::NOW,
            'nonce' => 'nonce-1',
            'license_payload_b64' => $package['payload'],
            'integrity' => $package['envelope'],
        ], $bodyOverrides);

        $raw = $rawBody ?? (string) json_encode($body, JSON_UNESCAPED_SLASHES);

        $message = implode("\n", [
            'POST',
            Endpoint::updaterPath(),
            'req-1',
            (string) self::NOW,
            'nonce-1',
            hash('sha256', $raw),
        ]);

        $request = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], $raw);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-VT-Request-ID', 'req-1');
        $request->headers->set('X-VT-Timestamp', (string) self::NOW);
        $request->headers->set('X-VT-Nonce', 'nonce-1');
        $request->headers->set('X-VT-Key-ID', 'test-key');
        $request->headers->set('X-VT-Signature', $this->sign($message));

        return $request;
    }
}
