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
 * The immutable answer to "what is this installation entitled to right now?".
 *
 * Shared as INPUT to the gates spread across the bundle; it is not itself a
 * switch. There is deliberately no setter, no mutable flag and no global
 * "unlock" here: each protected boundary asks this object and enforces its own
 * decision, so removing or patching one call site cannot open the others.
 *
 * The licence key is intentionally absent — telemetry that needs it reads it
 * from the authenticated record behind an explicit accessor instead of having
 * it travel through every gate.
 */
final class EntitlementState
{
    public const LICENSED = 'licensed';

    public const ABSENT = 'unlicensed_absent';

    public const UNVERIFIABLE = 'unlicensed_unverifiable';

    public const NO_HOST_MATCH = 'unlicensed_no_host_match';

    public const PACKAGE_NOT_ACCEPTED = 'unlicensed_package_not_accepted';

    public const NOT_STARTED = 'unlicensed_not_started';

    public const EXPIRED = 'unlicensed_expired';

    public const NEEDS_REFRESH = 'unlicensed_needs_refresh';

    /**
     * @param list<string> $features
     * @param list<string> $signedHosts
     * @param list<string> $configuredHosts
     */
    private function __construct(
        public readonly bool $licensed,
        public readonly string $status,
        public readonly string $package,
        public readonly array $features,
        public readonly ?string $matchedHost,
        public readonly array $signedHosts,
        public readonly array $configuredHosts,
        public readonly int $version,
        public readonly ?int $expiresAt,
        public readonly bool $lifetime,
    ) {
    }

    /**
     * @param list<string> $features
     * @param list<string> $signedHosts
     * @param list<string> $configuredHosts
     */
    public static function granted(
        string $package,
        array $features,
        string $matchedHost,
        array $signedHosts,
        array $configuredHosts,
        int $version,
        ?int $expiresAt,
        bool $lifetime,
    ): self {
        return new self(true, self::LICENSED, $package, $features, $matchedHost, $signedHosts, $configuredHosts, $version, $expiresAt, $lifetime);
    }

    /**
     * @param list<string> $signedHosts
     * @param list<string> $configuredHosts
     */
    public static function withheld(
        string $status,
        array $configuredHosts = [],
        array $signedHosts = [],
        int $version = 0,
        ?int $expiresAt = null,
        string $package = '',
    ): self {
        return new self(false, $status, $package, [], null, $signedHosts, $configuredHosts, $version, $expiresAt, false);
    }

    /** The product identity this state belongs to. */
    public function project(): string
    {
        return PackagePolicy::PROJECT;
    }

    /** This product uses one instance-wide state; there is no per-site scope. */
    public function isGlobalScope(): bool
    {
        return true;
    }

    /**
     * Optional per-feature narrowing. An empty signed feature list means "the
     * whole accepted package", which is how this product is sold; an explicit
     * list restricts to exactly those identifiers.
     */
    public function allowsFeature(string $feature): bool
    {
        if (!$this->licensed) {
            return false;
        }

        return $this->features === [] || \in_array($feature, $this->features, true);
    }

    public function hasStoredRecord(): bool
    {
        return $this->status !== self::ABSENT;
    }
}
