<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Feature\LlmsTxt\LlmsTxtBuilder;

/**
 * Public /llms.txt route (llmstxt.org). 404s when the feature is disabled —
 * zero trace, as required.
 */
final class LlmsTxtController
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly LlmsTxtBuilder $builder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
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
