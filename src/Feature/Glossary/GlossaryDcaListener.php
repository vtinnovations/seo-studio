<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\Glossary;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use VTinnovations\SeoStudio\Core\Config\FeatureState;
use VTinnovations\SeoStudio\Core\Config\Translations;

/**
 * Injects the "SEO-Titel & -Beschreibung mit KI" panel into the glossary
 * entry form — same UX as the tl_page meta panel; the JS fills
 * ctrl_metaTitle / ctrl_metaDescription, the editor reviews and saves.
 */
#[AsHook('loadDataContainer')]
final class GlossaryDcaListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ($table !== 'tl_seo_studio_glossary' || !$this->featureState->isEnabled('glossary')) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_seo_studio_glossary']['fields']['seoStudioGlossaryPanel'] = [
            'input_field_callback' => fn (DataContainer $dc): string => $this->renderPanel($dc),
            'eval' => ['doNotSaveEmpty' => true],
        ];

        PaletteManipulator::create()
            ->addField('seoStudioGlossaryPanel', 'metaDescription', PaletteManipulator::POSITION_AFTER)
            ->applyToPalette('default', 'tl_seo_studio_glossary');
    }

    private function renderPanel(DataContainer $dc): string
    {
        $token = $this->csrfTokenManager->getDefaultTokenValue();
        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $GLOBALS['TL_JAVASCRIPT']['seo_studio_meta'] = 'bundles/vtinnovationsseostudio/meta-panel.js';
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        return '<div class="widget clr seo-studio-panel" data-seo-studio-meta'
            . ' data-page-id="' . (int) $dc->id . '"'
            . ' data-id-param="entryId"'
            . ' data-target-title="metaTitle"'
            . ' data-target-description="metaDescription"'
            . ' data-token="' . htmlspecialchars($token, ENT_QUOTES) . '"'
            . ' data-url="' . htmlspecialchars('/contao/seostudio/glossary/meta', ENT_QUOTES) . '">'
            . '<h3>SEO Studio</h3>'
            . '<p><button type="button" class="tl_submit seo-studio-generate">' . $e(Translations::text('glossary.proposeMetaButton')) . '</button>'
            . ' <span class="seo-studio-status" aria-live="polite"></span></p>'
            . '<div class="seo-studio-result" hidden>'
            . '<p class="seo-studio-proposal"><strong>' . $e(Translations::text('meta.titleLabel')) . ':</strong> <span data-role="pageTitle"></span> <em data-role="pageTitleLen"></em></p>'
            . '<p class="seo-studio-proposal"><strong>' . $e(Translations::text('meta.descriptionLabel')) . ':</strong> <span data-role="description"></span> <em data-role="descriptionLen"></em></p>'
            . '<p><button type="button" class="tl_submit seo-studio-apply">' . $e(Translations::text('meta.applyButton')) . '</button> '
            . '<button type="button" class="tl_submit seo-studio-discard">Verwerfen</button></p>'
            . '<p class="tl_help">' . $e(Translations::text('meta.applyHint')) . '</p>'
            . '</div></div>';
    }
}
