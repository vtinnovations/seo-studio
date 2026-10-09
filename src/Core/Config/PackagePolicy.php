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

/**
 * Product identity and tier policy.
 *
 * Two different kinds of identifier live here, and they are compared
 * differently on purpose:
 *
 *   - PROJECT_SLUG and PRODUCT_ID are MACHINE identifiers. They are compared
 *     byte-for-byte against every signed document, and they are what actually
 *     prevents a licence issued for another product from being accepted here.
 *   - PROJECT is the human-readable catalogue TITLE. The vendor catalogue
 *     spells it with a space ("SEO Studio") while the wire protocol has always
 *     sent the compact form ("SeoStudio"), so it is compared through
 *     projectMatches() rather than byte-for-byte. Pinning a display name
 *     exactly across two independently maintained systems is what caused
 *     genuine, correctly signed licences to be rejected as "product_mismatch".
 *
 * Relaxing the title comparison costs nothing in trust: the title travels
 * inside the same Ed25519-signed document as the slug, so an attacker who
 * could choose it could equally choose the slug. The slug check stays exact.
 *
 * Tier model: PRO ONLY. There is no free tier, no trial and no post-expiry
 * fallback in this product:
 *   - only the packages in ACCEPTED_PACKAGES may authorise anything;
 *   - "free_available" carries no authority whatsoever and is never consulted
 *     for entitlement;
 *   - a missing, removed, not-yet-valid, expired or unverifiable record means
 *     unlicensed, and unlicensed means the bundle contributes nothing (exact
 *     Contao default behaviour).
 */
final class PackagePolicy
{
    /**
     * Product name exchanged in every outbound packet.
     *
     * Kept in the compact spelling because that is the form the vendor
     * endpoint has always been sent and accepts. Inbound titles are matched
     * with projectMatches(), so the catalogue is free to spell it differently.
     */
    public const PROJECT = 'SeoStudio';

    /** Route-safe identifier; also part of the inbound updater path. */
    public const PROJECT_SLUG = 'seo-studio';

    /** Vendor catalogue identifier. */
    public const PRODUCT_ID = 'vt-seo-studio';

    /** Administrator-facing product title. */
    public const TITLE = 'AI SEO Studio';

    /** Selected tier model. */
    public const MODEL = 'pro_only';

    /**
     * The only package values that may authorise this product.
     *
     * @var list<string>
     */
    public const ACCEPTED_PACKAGES = ['pro'];

    /** Longest licence key accepted from an administrator form. */
    public const KEY_MAX_LENGTH = 191;

    /**
     * How long a verified licence may run before it has to be confirmed with
     * the vendor again, and how much longer it may keep running while those
     * confirmations are failing. 30 days, then 14 days of grace.
     *
     * WHY A LEASE EXISTS AT ALL. A vendor push closes the domain-transfer hole
     * only when the push arrives. An installation that has moved on can be
     * offline, behind changed DNS, firewalled, or deliberately dropping our
     * requests — and deliberately dropping them is exactly what someone
     * exploiting the hole does. So the push cannot be the enforcement; the
     * enforcement is that entitlement is a LEASE which has to be renewed, and
     * an installation that is never reachable runs out of it on its own.
     *
     * Deliberately compile-time constants and NOT container parameters: the
     * site owner is who this guard is against, and a parameter would let them
     * set the lease to a century. The signed license_refresh_required_at /
     * license_grace_until fields win outright when the vendor sends them, so
     * these two numbers are the fallback for a server that does not yet.
     */
    public const LEASE_SECONDS = 2592000;

    public const GRACE_SECONDS = 1209600;

    private function __construct()
    {
    }

    public static function acceptsPackage(string $package): bool
    {
        return \in_array($package, self::ACCEPTED_PACKAGES, true);
    }

    /**
     * When this record has to be confirmed with the vendor again.
     *
     * The signed deadline when the vendor states one, otherwise LEASE_SECONDS
     * after the licence was last confirmed. Either way the answer comes out of
     * the SIGNED document — never out of a file the site owner can edit, which
     * is what made the previous generation of these clients trivially
     * defeatable.
     */
    public static function recheckDueAt(ProvisioningRecord $record): int
    {
        return $record->refreshRequiredAt() ?? ($record->leaseAnchor() + self::LEASE_SECONDS);
    }

    /**
     * The hard cutoff. Past this, protected entitlement fails closed until a
     * newer valid signed state is obtained.
     *
     * Reaching it means every re-check has failed for the whole lease plus the
     * whole grace period, because each success restamps license_verified_at and
     * moves both deadlines forward.
     */
    public static function graceEndsAt(ProvisioningRecord $record): int
    {
        return $record->graceUntil() ?? (self::recheckDueAt($record) + self::GRACE_SECONDS);
    }

    /** True when stale entitlement must no longer be honoured. */
    public static function leaseHasLapsed(ProvisioningRecord $record, int $now): bool
    {
        return $now > self::graceEndsAt($record);
    }

    /**
     * Whether a project TITLE from a signed packet names this product.
     *
     * Compares on letters and digits only, case-insensitively, so that
     * "SEO Studio", "SeoStudio" and "seo-studio" are one identity while
     * anything genuinely different ("FAQ Studio") still fails. This is a
     * display-name check only — the slug is what carries product identity and
     * it is still compared byte-for-byte by the caller.
     */
    public static function projectMatches(mixed $title): bool
    {
        if (!\is_string($title) || $title === '') {
            return false;
        }

        return self::foldTitle($title) === self::foldTitle(self::PROJECT);
    }

    /**
     * Reduces a title to its comparable core: ASCII lowercase alphanumerics.
     */
    private static function foldTitle(string $title): string
    {
        $folded = preg_replace('/[^a-z0-9]+/', '', strtolower($title));

        return $folded ?? '';
    }

    /**
     * Shape check for a key typed into the backend form. Deliberately liberal
     * about the vendor's key format (only the vendor may judge that) while
     * refusing whitespace, control characters and absurd lengths locally.
     */
    public static function keyLooksWellFormed(string $key): bool
    {
        if ($key === '' || \strlen($key) > self::KEY_MAX_LENGTH) {
            return false;
        }

        return preg_match('/^[\x21-\x7e]+$/', $key) === 1;
    }
}
