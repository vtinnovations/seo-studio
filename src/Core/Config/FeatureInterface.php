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
 * A toggleable feature module. Implementations are collected via the
 * "seo_studio.feature" tag (autoconfigured) into the FeatureRegistry.
 *
 * Core knows nothing about concrete features; the settings UI renders one
 * toggle per registered feature, gated by license tier. A disabled feature
 * must leave ZERO trace in the backend — every DCA hook / listener of the
 * feature checks FeatureState::isEnabled() first.
 */
interface FeatureInterface
{
    /** Config key suffix, e.g. "meta" -> toggle key "featureMeta". */
    public function getId(): string;

    /** Translation key under TL_LANG.SEO_STUDIO.features.<id>; fallback label. */
    public function getLabel(): string;

    public function getRequiredTier(): LicenseTier;
}
