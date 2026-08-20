<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Social;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\PageModel;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Renders the social-media preview card shown in the tl_page edit form:
 * a Facebook/LinkedIn/X-style card built from the current (saved) values.
 * The client (social.js) live-updates title/description as the editor types.
 */
final class SocialPreviewRenderer
{
    public function __construct(
        private readonly ContaoFramework $framework,
        private readonly SocialTagBuilder $builder,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function render(int $pageId): string
    {
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';
        $GLOBALS['TL_JAVASCRIPT']['seo_studio_social'] = 'bundles/vtinnovationsseostudio/social.js';

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->framework->initialize();
        $pageAdapter = $this->framework->getAdapter(PageModel::class);
        $page = $pageAdapter->findByPk($pageId);

        if ($page === null) {
            return '<div class="widget clr"><p class="tl_info">' . $e(Translations::text('social.notSavedYet')) . '</p></div>';
        }

        // Pull resolved values out of the tag builder so preview == output.
        $values = [];
        foreach ($this->builder->build($page) as [, $key, $content]) {
            $values[$key] = $content;
        }

        $title = $values['og:title'] ?? '';
        $desc = $values['og:description'] ?? '';
        $image = $values['og:image'] ?? '';
        $domain = $this->domain();

        $imgHtml = $image !== ''
            ? '<span class="seo-studio-socialcard-img" data-role="image" style="background-image:url(' . $e($image) . ')"></span>'
            : '<span class="seo-studio-socialcard-img seo-studio-socialcard-img--empty" data-role="image">Kein Bild gesetzt</span>';

        return '<div class="widget clr seo-studio-social" data-seo-studio-social'
            . ' data-fallback-title="' . $e($title) . '"'
            . ' data-fallback-desc="' . $e($desc) . '">'
            . '<h3 style="margin-bottom:8px">Social-Media-Vorschau</h3>'
            . '<div class="seo-studio-socialcard">'
            . $imgHtml
            . '<span class="seo-studio-socialcard-body">'
            . '<span class="seo-studio-socialcard-domain">' . $e($domain) . '</span>'
            . '<span class="seo-studio-socialcard-title" data-role="title">' . $e($title !== '' ? $title : Translations::text('social.noTitle')) . '</span>'
            . '<span class="seo-studio-socialcard-desc" data-role="desc">' . $e($desc) . '</span>'
            . '</span></div>'
            . '<p class="tl_help" style="margin-top:8px">' . $e(Translations::text('social.previewHint')) . '</p>'
            . '</div>';
    }

    private function domain(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request !== null ? $request->getHost() : '';
    }
}
