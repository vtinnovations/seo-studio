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
 * A toggleable feature module. Implementations are collected via the
 * "seo_studio.feature" tag (autoconfigured) into the FeatureRegistry.
 *
 * Core knows nothing about concrete features; the settings UI renders one
 * toggle per registered feature, labelled from TL_LANG.SEO_STUDIO.features.<id>
 * so no feature carries a hardcoded label. A disabled feature must leave ZERO trace in
 * the backend — every DCA hook / listener of the feature checks
 * FeatureState::isEnabled() first.
 */
interface FeatureInterface
{
    /** Config key suffix, e.g. "meta" -> toggle key "featureMeta". */
    public function getId(): string;
}
