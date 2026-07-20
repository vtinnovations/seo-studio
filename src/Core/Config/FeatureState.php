<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Core\Config;

/**
 * Single source of truth for "is feature X active right now?".
 *
 * active = toggle on AND license tier sufficient. Cheap (ConfigProvider is
 * request-cached), safe to call from loadDataContainer hooks.
 */
final class FeatureState
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly FeatureRegistry $registry,
        private readonly \VTinnovations\SeoStudio\Core\Security\LicenseGuard $licenseGuard,
    ) {
    }

    public function isEnabled(string $featureId): bool
    {
        $feature = $this->registry->get($featureId);
        if ($feature === null) {
            return false;
        }

        if (!$this->tierAllows($feature)) {
            return false;
        }

        // Licence gate: locked features vanish completely (demo / expired).
        if (!$this->licenseGuard->allowsFeature($featureId)) {
            return false;
        }

        return (bool) $this->config->get($this->toggleKey($featureId), false);
    }

    public function tierAllows(FeatureInterface $feature): bool
    {
        return $this->currentTier()->includes($feature->getRequiredTier());
    }

    public function currentTier(): LicenseTier
    {
        return LicenseTier::tryFrom((string) $this->config->get('licenseTier', 'free')) ?? LicenseTier::Free;
    }

    public function toggleKey(string $featureId): string
    {
        return 'feature' . ucfirst($featureId);
    }
}
