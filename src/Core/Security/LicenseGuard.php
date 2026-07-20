<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Security;

/**
 * Single licence gate for the whole bundle. Resolves the current state
 * (licensed / demo / unlicensed) and decides which features are allowed.
 *
 * EVERY unlock needs a server-verified key — there is no local auto-trial. A
 * demo key (server package "demo"/"trial") unlocks the CORE evaluation set —
 * per-page SEO score, the analysis reports, Schema.org, the social preview and
 * AI meta — so a buyer sees the value; everything else is full-version only.
 */
final class LicenseGuard
{
    public const STATE_LICENSED = 'licensed';
    public const STATE_DEMO = 'demo';
    public const STATE_UNLICENSED = 'unlicensed';

    /**
     * Feature IDs a demo licence unlocks.
     *
     * @var list<string>
     */
    private const DEMO_FEATURES = ['pageScore', 'audit', 'schema', 'social', 'meta'];

    public function __construct(private readonly LicenseManager $licenseManager)
    {
    }

    public function isLicensed(): bool
    {
        return $this->licenseManager->isLicensed();
    }

    public function state(): string
    {
        if (!$this->licenseManager->isLicensed()) {
            return self::STATE_UNLICENSED;
        }

        return $this->licenseManager->isDemoPackage() ? self::STATE_DEMO : self::STATE_LICENSED;
    }

    public function isDemo(): bool
    {
        return self::STATE_DEMO === $this->state();
    }

    public function isUnlicensed(): bool
    {
        return self::STATE_UNLICENSED === $this->state();
    }

    /**
     * Whether a given feature id may run under the current licence state.
     */
    public function allowsFeature(string $featureId): bool
    {
        return match ($this->state()) {
            self::STATE_LICENSED => true,
            self::STATE_DEMO => \in_array($featureId, self::DEMO_FEATURES, true),
            default => false,
        };
    }

    public function isDemoFeature(string $featureId): bool
    {
        return \in_array($featureId, self::DEMO_FEATURES, true);
    }

    /**
     * Whole days left on a demo licence (from the server expiry), 0 if none.
     */
    public function demoDaysLeft(): int
    {
        $expires = $this->licenseManager->getExpiresAt();
        if ($expires === null) {
            return 0;
        }

        return max(0, (int) ceil(($expires - time()) / 86400));
    }
}
