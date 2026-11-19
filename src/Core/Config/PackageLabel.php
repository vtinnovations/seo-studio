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
 * DISPLAY ONLY: which of the three package names the licence section shows —
 * Free, Pro or Demo. Nothing that grants, limits or gates may call this;
 * entitlement stays with PackagePolicy::acceptsPackage() on the raw package.
 *
 * Resolution order, first match wins:
 *   1. the signed license_is_demo flag is exactly true  → demo
 *   2. the package string is "demo" (older vocabulary)  → demo
 *   3. the package string is "free"                     → free
 *   4. the package string is "pro"                      → pro
 *
 * Demo is never inferred from anything else — not the term, the lifetime
 * flag, the expiry, the feature list or free_available. A demo is a
 * time-limited Pro whose package string is "pro", so the flag is the only
 * thing that tells the two apart; a free fallback drops the flag and is Free.
 */
final class PackageLabel
{
    public const DEMO = 'demo';

    public const FREE = 'free';

    public const PRO = 'pro';

    private function __construct()
    {
    }

    /**
     * @param mixed $demoFlag the raw license_is_demo value; anything but true
     *                        (missing, null, false, "yes", 1, …) counts as false
     *
     * @return self::DEMO|self::FREE|self::PRO|null null for a package string
     *                                              this bundle does not know
     */
    public static function resolve(string $package, mixed $demoFlag = null): ?string
    {
        if ($demoFlag === true) {
            return self::DEMO;
        }

        return match (strtolower(trim($package))) {
            self::DEMO => self::DEMO,
            self::FREE => self::FREE,
            self::PRO => self::PRO,
            default => null,
        };
    }

    /**
     * The translated value for the licence section. An unknown package string
     * is shown as sent rather than guessed at (and never upper-cased).
     */
    public static function text(string $package, mixed $demoFlag = null): string
    {
        $resolved = self::resolve($package, $demoFlag);

        return match ($resolved) {
            self::DEMO => Translations::text('licence.packageDemo'),
            self::FREE => Translations::text('licence.packageFree'),
            self::PRO => Translations::text('licence.packagePro'),
            default => $package,
        };
    }
}
