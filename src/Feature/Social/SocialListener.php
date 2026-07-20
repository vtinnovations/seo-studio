<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Social;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\PageModel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Injects Open Graph + Twitter/X meta tags before </head> on frontend pages.
 *
 * Like the JSON-LD listener this runs on kernel.response (NOT via TL_HEAD),
 * because page-builder templates drop TL_HEAD. If the page template already
 * emits an og:title (theme or another extension), we stand down to avoid
 * duplicate tags.
 */
#[AsEventListener(priority: -768)]
final class SocialListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly ScopeMatcher $scopeMatcher,
        private readonly SocialTagBuilder $builder,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->scopeMatcher->isFrontendRequest($event->getRequest())) {
            return;
        }

        if (!$this->featureState->isEnabled('social')) {
            return;
        }

        $pageModel = $event->getRequest()->attributes->get('pageModel');
        if (!$pageModel instanceof PageModel) {
            return;
        }

        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || !str_contains($content, '</head>')) {
            return;
        }

        // Don't double up if the template already ships Open Graph tags.
        if (preg_match('/<meta[^>]+property\s*=\s*["\']og:title["\']/i', $content)) {
            return;
        }

        $tags = $this->builder->toHtml($pageModel);
        if (trim($tags) === '') {
            return;
        }

        $pos = strpos($content, '</head>');
        $response->setContent(substr($content, 0, (int) $pos) . $tags . substr($content, (int) $pos));
    }
}
