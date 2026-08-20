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

/**
 * The trusted host identity of this installation.
 *
 * Kept as an interface so the host policy stays separable from the Contao/DBAL
 * lookup that provides it: verification and entitlement code depends on this
 * contract only, which is also what makes those decisions testable against
 * fixed host sets without a database.
 *
 * Implementations must derive hosts from CONFIGURATION and return canonical
 * values (see HostName); they must never take a host from a request header.
 */
interface HostInventory
{
    /**
     * Canonical, unique, sorted configured hosts of this installation.
     *
     * @return list<string>
     */
    public function configuredHosts(): array;

    /**
     * The exact hosts present in both the configured inventory and a signed
     * host set. Exact membership only — no suffix, wildcard or parent logic.
     *
     * @param list<string> $signedHosts
     *
     * @return list<string>
     */
    public function intersect(array $signedHosts): array;

    /**
     * The deterministic host to report for the given signed set, or null when
     * this installation is not covered by it at all.
     *
     * @param list<string> $signedHosts
     */
    public function matchedHost(array $signedHosts): ?string;

    /** The host to send in an activation/refresh packet, if any. */
    public function outboundHost(): ?string;

    /** Drops any per-process cache. */
    public function reset(): void;
}
