<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Feature\LlmsTxt\LlmsTxtBuilder;

/**
 * Public /llms.txt route (llmstxt.org). 404s when the feature is disabled or the
 * instance is unlicensed — zero trace, exactly as if the bundle were absent.
 */
final class LlmsTxtController
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly LlmsTxtBuilder $builder,
        private readonly EntitlementEvaluator $entitlement,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->entitlement->isLicensed()) {
            return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (!$this->featureState->isEnabled('llmsTxt')) {
            return new Response('Not Found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $content = $this->builder->build($request->getHost());

        return new Response($content, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
