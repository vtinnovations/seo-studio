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
 * Single source of truth for "is feature X active right now?".
 *
 * active = the instance is licensed for the feature AND the feature is
 * registered AND its config toggle is on. Cheap (ConfigProvider and the
 * entitlement result are both request-cached), safe to call from
 * loadDataContainer hooks.
 *
 * This is the BROAD gate that keeps the backend surface out of an unlicensed
 * installation. It is deliberately not the only one: every functional boundary
 * (AJAX endpoint, backend module, cron, frontend listener, frontend module, AI
 * gateway) checks entitlement independently, so patching or removing this one
 * method does not unlock anything.
 */
final class FeatureState
{
    public function __construct(
        private readonly ConfigProvider $config,
        private readonly FeatureRegistry $registry,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function isEnabled(string $featureId): bool
    {
        if ($this->registry->get($featureId) === null) {
            return false;
        }

        if (!$this->entitlement->current()->allowsFeature($featureId)) {
            return false;
        }

        return (bool) $this->config->get($this->toggleKey($featureId), false);
    }

    public function toggleKey(string $featureId): string
    {
        return 'feature' . ucfirst($featureId);
    }
}
