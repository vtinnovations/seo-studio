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

    /**
     * Whether this installation serves the given host at all — configured
     * identity plus the host the current request actually arrived on.
     *
     * This answers ADDRESSING, not entitlement, and it exists for withdrawals
     * specifically. A revoked host is absent from the signed set by
     * construction, so it cannot be recognised the way a grant's host is; the
     * only remaining question is whether the packet was addressed to us.
     *
     * It is load-bearing rather than defence in depth: the vendor probes a
     * released slot at both the apex and its "www." host and stops at the first
     * acknowledgement. Without this check an installation would acknowledge a
     * withdrawal for a host it does not serve, the vendor would consider the
     * transfer done, and the host that really needed telling would never be
     * told.
     */
    public function owns(string $host): bool;

    /** Drops any per-process cache. */
    public function reset(): void;
}
