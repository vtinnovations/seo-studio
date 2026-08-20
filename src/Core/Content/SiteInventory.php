<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Content;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The instance's trusted host inventory.
 *
 * Sources are CONFIGURATION, never a request header:
 *   1. the domain configured on each site root (tl_page.dns),
 *   2. plain host names from Symfony's trusted-host allowlist, when the
 *      installation maintains one.
 *
 * The current request host is used only to *choose between* hosts that are
 * already in that inventory, so a forged Host/X-Forwarded-Host header cannot
 * introduce an identity of its own. Symfony::getHost() already applies the
 * trusted-proxy and trusted-host configuration of the installation.
 *
 * A Contao install whose roots have an empty "dns" answers on any host and
 * therefore has no configured identity — the inventory is then empty and the
 * administrator is asked to configure the site root domain. Guessing one from
 * the current request would be exactly the spoofable behaviour we refuse.
 */
final class SiteInventory implements HostInventory
{
    /** @var list<string>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Every trusted configured host of this installation, canonical and sorted.
     *
     * @return list<string>
     */
    public function configuredHosts(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $hosts = [];

        try {
            /** @var list<string> $rows */
            $rows = $this->connection->fetchFirstColumn(
                "SELECT dns FROM tl_page WHERE type = 'root' AND dns != '' ORDER BY sorting, id",
            );
            $hosts = $rows;
        } catch (\Throwable) {
            // Pre-migration or unavailable database: no configured identity.
            $hosts = [];
        }

        foreach (Request::getTrustedHosts() as $pattern) {
            // Trusted hosts may be regular expressions; only literal host
            // names are usable as an identity.
            if (preg_match('/^[A-Za-z0-9.\-]+$/', $pattern) === 1) {
                $hosts[] = $pattern;
            }
        }

        return $this->cache = HostName::canonicalSet($hosts);
    }

    /**
     * The site-root domain Contao itself would treat as first (sorting order),
     * used as the deterministic fallback when the current request host is not
     * part of the inventory.
     */
    public function primaryHost(): ?string
    {
        try {
            /** @var list<string> $rows */
            $rows = $this->connection->fetchFirstColumn(
                "SELECT dns FROM tl_page WHERE type = 'root' AND dns != '' ORDER BY sorting, id",
            );
        } catch (\Throwable) {
            $rows = [];
        }

        foreach ($rows as $dns) {
            $host = HostName::normalize($dns);
            if ($host !== null) {
                return $host;
            }
        }

        return $this->configuredHosts()[0] ?? null;
    }

    /**
     * The current request host, but only when it is part of the configured
     * inventory. Returns null in CLI/cron context and for any host the
     * installation has not configured.
     */
    public function currentTrustedHost(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        try {
            $host = HostName::normalize($request->getHost());
        } catch (\Throwable) {
            // Symfony rejects a host that violates the trusted-host config.
            return null;
        }

        if ($host === null || !\in_array($host, $this->configuredHosts(), true)) {
            return null;
        }

        return $host;
    }

    /**
     * The host sent as "domain" in an activation/refresh packet: the current
     * trusted host when it is configured, otherwise the primary configured
     * host. Null means "this installation has no configured identity" and the
     * operation must be refused.
     */
    public function outboundHost(): ?string
    {
        return $this->currentTrustedHost() ?? $this->primaryHost();
    }

    /**
     * The exact hosts present in BOTH the configured inventory and a signed
     * host set. This intersection — never a suffix or wildcard rule — is what
     * authorises this installation.
     *
     * @param list<string> $signedHosts
     *
     * @return list<string>
     */
    public function intersect(array $signedHosts): array
    {
        $configured = $this->configuredHosts();
        $out = [];

        foreach ($signedHosts as $host) {
            if (\in_array($host, $configured, true)) {
                $out[] = $host;
            }
        }

        return $out;
    }

    /**
     * The host to report in server-to-server signals for the given signed set:
     * the current trusted host when it is itself authorised, otherwise the
     * deterministic first authorised host. Keeps CLI, cron and queue work
     * independent of whatever Host header happens to be current.
     *
     * @param list<string> $signedHosts
     */
    public function matchedHost(array $signedHosts): ?string
    {
        $intersection = $this->intersect($signedHosts);
        if ($intersection === []) {
            return null;
        }

        $current = $this->currentTrustedHost();
        if ($current !== null && \in_array($current, $intersection, true)) {
            return $current;
        }

        return $intersection[0];
    }

    /**
     * Test seam / long-running worker seam: forget the per-process host cache.
     */
    public function reset(): void
    {
        $this->cache = null;
    }
}
