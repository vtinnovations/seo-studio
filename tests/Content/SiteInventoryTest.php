<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Content;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\SeoStudio\Core\Content\SiteInventory;

/**
 * The trusted host inventory comes from configuration, never from a header.
 */
final class SiteInventoryTest extends TestCase
{
    public function testInventoryComesFromConfiguredRootDomains(): void
    {
        $inventory = $this->inventory(['Example.COM', 'staging.example.com'], 'example.com');

        self::assertSame(['example.com', 'staging.example.com'], $inventory->configuredHosts());
    }

    public function testAnUnconfiguredRequestHostIsIgnored(): void
    {
        // The request claims a host the installation never configured.
        $inventory = $this->inventory(['example.com'], 'attacker.example.net');

        self::assertNull($inventory->currentTrustedHost());
        self::assertSame(['example.com'], $inventory->configuredHosts());
        // The outbound domain falls back to the configured primary.
        self::assertSame('example.com', $inventory->outboundHost());
    }

    public function testASpoofedHostCannotEnterTheInventory(): void
    {
        $inventory = $this->inventory(['example.com'], 'evil.test');

        self::assertNotContains('evil.test', $inventory->configuredHosts());
        self::assertSame([], $inventory->intersect(['evil.test']));
        self::assertNull($inventory->matchedHost(['evil.test']));
    }

    public function testTheCurrentHostWinsWhenItIsConfigured(): void
    {
        $inventory = $this->inventory(['a.example.com', 'b.example.com'], 'b.example.com');

        self::assertSame('b.example.com', $inventory->outboundHost());
        self::assertSame('b.example.com', $inventory->matchedHost(['a.example.com', 'b.example.com']));
    }

    public function testTheFirstRootIsThePrimaryFallback(): void
    {
        $inventory = $this->inventory(['first.example.com', 'second.example.com'], null);

        self::assertSame('first.example.com', $inventory->primaryHost());
        self::assertSame('first.example.com', $inventory->outboundHost());
    }

    public function testAnInstallationWithoutAConfiguredDomainHasNoIdentity(): void
    {
        $inventory = $this->inventory([], 'example.com');

        self::assertSame([], $inventory->configuredHosts());
        self::assertNull($inventory->outboundHost());
        self::assertNull($inventory->matchedHost(['example.com']));
    }

    public function testIntersectionIsExactNotSuffixBased(): void
    {
        $inventory = $this->inventory(['shop.example.com'], 'shop.example.com');

        self::assertSame([], $inventory->intersect(['example.com']), 'a parent domain must not match');
        self::assertSame([], $inventory->intersect(['www.example.com']), 'a sibling must not match');
        self::assertSame([], $inventory->intersect(['admin.shop.example.com']), 'a child must not match');
        self::assertSame(['shop.example.com'], $inventory->intersect(['shop.example.com']));
    }

    public function testAnUnavailableDatabaseYieldsNoIdentityInsteadOfGuessing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willThrowException(new \RuntimeException('no tl_page yet'));

        $stack = new RequestStack();
        $stack->push(Request::create('https://example.com/'));

        $inventory = new SiteInventory($connection, $stack);

        self::assertSame([], $inventory->configuredHosts());
        self::assertNull($inventory->outboundHost());
    }

    public function testCliContextHasNoCurrentHost(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['example.com']);

        $inventory = new SiteInventory($connection, new RequestStack());

        self::assertNull($inventory->currentTrustedHost());
        // Background work still resolves a deterministic configured host.
        self::assertSame('example.com', $inventory->outboundHost());
    }

    /**
     * @param list<string> $configured
     */
    private function inventory(array $configured, ?string $requestHost): SiteInventory
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($configured);

        $stack = new RequestStack();

        if ($requestHost !== null) {
            $stack->push(Request::create('https://' . $requestHost . '/contao'));
        }

        return new SiteInventory($connection, $stack);
    }
}
