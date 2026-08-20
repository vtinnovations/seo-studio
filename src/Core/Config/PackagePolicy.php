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

    private function __construct()
    {
    }

    public static function acceptsPackage(string $package): bool
    {
        return \in_array($package, self::ACCEPTED_PACKAGES, true);
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
