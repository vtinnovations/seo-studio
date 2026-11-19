<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\WordCount;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Live word + character counter under every TinyMCE editor in the backend.
 *
 * UPDATE-SAFE BY DESIGN: nothing of Contao is replaced — no be_tinyMCE
 * template override, no DCA change, no TinyMCE config change. A script sits
 * NEXT TO each editor and only reads from it (tinymce "AddEditor" event +
 * getContent({format: 'text'}), both stable since TinyMCE 4). If a future
 * Contao drops or changes the editor, the script finds nothing and does
 * nothing; editing keeps working.
 *
 * Loaded for every DCA in a backend request (not limited to tl_content), so
 * news, events, FAQ and third-party RTE fields are covered too. On views
 * without an editor the script is a no-op.
 */
#[AsHook('loadDataContainer')]
final class WordCountListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly RequestStack $requestStack,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public function __invoke(string $table): void
    {
        // The DCA is also loaded in the frontend and during cache warmup —
        // the counter belongs to the backend only.
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$this->scopeMatcher->isBackendRequest($request)) {
            return;
        }

        if (isset($GLOBALS['TL_JAVASCRIPT']['seo_studio_wordcount']) || !$this->featureState->isEnabled('wordCount')) {
            return;
        }

        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';
        $GLOBALS['TL_JAVASCRIPT']['seo_studio_wordcount'] = 'bundles/vtinnovationsseostudio/wordcount.js';
        $GLOBALS['TL_MOOTOOLS'][] = '<script>window.SeoStudioWordCountConfig='
            . json_encode(['label' => Translations::text('wordCount.label')], JSON_THROW_ON_ERROR | JSON_HEX_TAG)
            . ';</script>';
    }
}
