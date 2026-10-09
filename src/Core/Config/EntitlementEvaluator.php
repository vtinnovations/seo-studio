<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Config;

use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;

/**
 * Turns the stored record into the current entitlement.
 *
 * Every evaluation re-verifies the stored pair cryptographically — signatures
 * first, exact-byte digest second — and then applies the product's tier model
 * (PRO ONLY):
 *
 *   no record                       -> unlicensed (framework default)
 *   record fails verification       -> unlicensed, previous files untouched
 *   record predates the host fields -> unlicensed until an administrator refresh
 *   no configured host in the set   -> unlicensed (copied to another install)
 *   package outside the allowlist   -> unlicensed
 *   not yet started / expired       -> unlicensed
 *   lease unconfirmed past grace    -> unlicensed
 *   otherwise                       -> licensed
 *
 * A stored WITHDRAWAL (the vendor's signed "this licence moved") is evaluated
 * through those same rules rather than short-circuited on its status, and that
 * is the whole design. A withdrawal carries the licence's CURRENT authorised
 * host set, so the ordinary intersection already answers every question it
 * raises: an installation that released its only host finds no configured host
 * in the set and goes dark, while one that serves several of the licence's
 * hosts and released just one keeps running on the rest. Short-circuiting on
 * the status instead took the whole installation down in the second case.
 *
 * It also makes the order the vendor's two packets arrive in irrelevant: either
 * way the record left on disk states the right host set.
 *
 * There is no trial and no free fallback: "free_available" is never consulted.
 * The only grace here is the lease's — a bounded allowance for the vendor being
 * temporarily unreachable, which expires rather than extends. Nothing local —
 * deleting files, clearing caches, editing the database, changing sessions,
 * reinstalling — can produce a licensed state.
 *
 * The result is cached per request only. It is never persisted, so a stale
 * cache can never outlive the record it describes.
 */
final class EntitlementEvaluator
{
    private ?EntitlementState $state = null;

    private ?ProvisioningRecord $authenticated = null;

    private bool $evaluated = false;

    public function __construct(
        private readonly ProvisioningStore $store,
        private readonly PackageAcceptance $acceptance,
        private readonly HostInventory $inventory,
    ) {
    }

    public function current(): EntitlementState
    {
        if (!$this->evaluated) {
            $this->evaluate(time());
        }

        \assert($this->state instanceof EntitlementState);

        return $this->state;
    }

    public function isLicensed(): bool
    {
        return $this->current()->licensed;
    }

    /**
     * The verified record, or null when nothing authentic is stored.
     *
     * The only legitimate reason to reach for this is the module-entry signal,
     * which must transmit the full key. Feature gates use current() instead so
     * the key does not travel through the whole bundle.
     */
    public function authenticatedRecord(): ?ProvisioningRecord
    {
        if (!$this->evaluated) {
            $this->evaluate(time());
        }

        return $this->authenticated;
    }

    /**
     * The stored version, for a refresh packet's "current_license_version".
     * Read straight from disk: a record that no longer verifies must still not
     * be replaceable by an older one.
     */
    public function storedVersion(): ?int
    {
        $record = $this->store->load();

        return $record?->version();
    }

    /** Called after activation, refresh, push or removal. */
    public function invalidate(): void
    {
        $this->state = null;
        $this->authenticated = null;
        $this->evaluated = false;
        $this->inventory->reset();
    }

    /**
     * Test seam: evaluate against a fixed clock.
     */
    public function evaluate(int $now): EntitlementState
    {
        $this->evaluated = true;
        $this->authenticated = null;

        $configured = $this->inventory->configuredHosts();
        $record = $this->store->load();

        if ($record === null) {
            return $this->state = EntitlementState::withheld(EntitlementState::ABSENT, $configured);
        }

        // A record issued before the signed host set existed is rollback
        // material only. It is never expanded locally with invented fields.
        if (!$record->hasHostSet()) {
            return $this->state = EntitlementState::withheld(
                EntitlementState::NEEDS_REFRESH,
                $configured,
                [],
                $record->version(),
            );
        }

        $verdict = $this->acceptance->checkStored($record, $now);

        if (!$verdict->isAccepted()) {
            $status = match ($verdict->category) {
                PackageAcceptance::NO_INTERSECTION => EntitlementState::NO_HOST_MATCH,
                PackageAcceptance::PACKAGE => EntitlementState::PACKAGE_NOT_ACCEPTED,
                // A record the rollback barrier has moved past: authentic, but
                // an older revision than one already accepted. In practice this
                // is a pre-transfer backup that has been restored, so the
                // administrator needs to be told the licence moved, not that
                // their files are broken.
                PackageAcceptance::SUPERSEDED => EntitlementState::REVOKED,
                default => EntitlementState::UNVERIFIABLE,
            };

            // Signatures and digest passed but the record does not entitle this
            // installation: keep it available to the module-entry signal only.
            if (PackageAcceptance::isVendorSigned($verdict->category)) {
                $this->authenticated = $record;
            }

            return $this->state = EntitlementState::withheld(
                $status,
                $configured,
                $record->domains(),
                $record->version(),
                $record->expiresAt(),
                $record->package(),
            );
        }

        if ($record->startsAfter($now)) {
            $this->authenticated = $record;

            return $this->state = EntitlementState::withheld(
                EntitlementState::NOT_STARTED,
                $configured,
                $record->domains(),
                $record->version(),
                $record->expiresAt(),
                $record->package(),
            );
        }

        // Two ways a term can be over, and both are licence-wide. The signed
        // status is the vendor stating it outright; hasExpiredAt() is this
        // installation reading the licence's own dates. Unlike a revocation,
        // neither is per-host, so there is nothing to intersect and the signed
        // host set does not get a say.
        if ($record->validationStatus() === ProvisioningRecord::STATUS_EXPIRED || $record->hasExpiredAt($now)) {
            // Pro Only: an expired package has no fallback tier whatsoever.
            $this->authenticated = $record;

            return $this->state = EntitlementState::withheld(
                EntitlementState::EXPIRED,
                $configured,
                $record->domains(),
                $record->version(),
                $record->expiresAt(),
                $record->package(),
            );
        }

        $matched = $this->inventory->matchedHost($record->domains());
        if ($matched === null) {
            // Same condition, two different things to tell the administrator.
            // A withdrawal means the licence is now somewhere else; anything
            // else means the configured domains are worth checking.
            $this->authenticated = $record;

            return $this->state = EntitlementState::withheld(
                $record->isWithdrawal() ? EntitlementState::REVOKED : EntitlementState::NO_HOST_MATCH,
                $configured,
                $record->domains(),
                $record->version(),
                $record->expiresAt(),
                $record->package(),
            );
        }

        // The lease. Everything above says the licence is genuine and covers a
        // host this installation serves; this asks whether the vendor has
        // confirmed it recently enough to still be trusted.
        //
        // It is the only enforcement that survives an installation the vendor
        // cannot reach — an abuser who firewalls the updater endpoint stops the
        // push, but cannot stop this clock, because both deadlines are computed
        // from fields INSIDE the signature.
        if (PackagePolicy::leaseHasLapsed($record, $now)) {
            $this->authenticated = $record;

            return $this->state = EntitlementState::withheld(
                EntitlementState::LEASE_EXPIRED,
                $configured,
                $record->domains(),
                $record->version(),
                $record->expiresAt(),
                $record->package(),
            );
        }

        // Authentic AND entitled.
        $this->authenticated = $record;

        return $this->state = EntitlementState::granted(
            $record->package(),
            $record->features(),
            $matched,
            $record->domains(),
            $configured,
            $record->version(),
            $record->expiresAt(),
            $record->isLifetime(),
        );
    }
}
