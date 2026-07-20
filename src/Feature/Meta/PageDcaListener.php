<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Meta;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Injects the "Mit KI generieren" panel into tl_page, right after the
 * description field. Zero trace when the meta feature is disabled.
 *
 * The panel is a virtual DCA field (no sql, input_field_callback) so it
 * renders inside the normal edit form without template overrides.
 */
#[AsHook('loadDataContainer')]
final class PageDcaListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ($table !== 'tl_page') {
            return;
        }

        if (!$this->featureState->isEnabled('meta') && !$this->featureState->isEnabled('faq')) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_page']['fields']['seoStudioMetaPanel'] = [
            // Closure keeps DI intact — Contao would instantiate a class
            // callback via `new` without constructor args.
            'input_field_callback' => fn (DataContainer $dc): string => $this->renderPanel($dc),
            'eval' => ['doNotSaveEmpty' => true],
        ];

        foreach (['regular', 'forward', 'redirect'] as $palette) {
            if (!isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])) {
                continue;
            }

            // Only palettes that actually contain the description field.
            if (!str_contains((string) $GLOBALS['TL_DCA']['tl_page']['palettes'][$palette], 'description')) {
                continue;
            }

            PaletteManipulator::create()
                ->addField('seoStudioMetaPanel', 'description', PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($palette, 'tl_page');
        }
    }

    public function renderPanel(DataContainer $dc): string
    {
        $token = $this->csrfTokenManager->getDefaultTokenValue();
        $pageId = (int) $dc->id;

        $GLOBALS['TL_JAVASCRIPT']['seo_studio_meta'] = 'bundles/vtinnovationsseostudio/meta-panel.js';
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        $metaSection = '';
        if ($this->featureState->isEnabled('meta')) {
            $metaSection = '<p><button type="button" class="tl_submit seo-studio-generate">Titel &amp; Beschreibung mit KI vorschlagen</button>'
                . ' <span class="seo-studio-status" aria-live="polite"></span></p>'
                . '<div class="seo-studio-result" hidden>'
                . '<p class="seo-studio-proposal"><strong>Titel:</strong> <span data-role="pageTitle"></span> <em data-role="pageTitleLen"></em></p>'
                . '<p class="seo-studio-proposal"><strong>Beschreibung:</strong> <span data-role="description"></span> <em data-role="descriptionLen"></em></p>'
                . '<p><button type="button" class="tl_submit seo-studio-apply">In Felder übernehmen</button> '
                . '<button type="button" class="tl_submit seo-studio-discard">Verwerfen</button></p>'
                . '<p class="tl_help">Übernahme füllt nur die Formularfelder — gespeichert wird erst mit „Speichern“.</p>'
                . '</div>';
        }

        $faqSection = '';
        if ($this->featureState->isEnabled('faq')) {
            $faqSection = '<p class="seo-studio-faq-row">'
                . '<button type="button" class="tl_submit seo-studio-faq-generate" data-url="/contao/seostudio/faq/generate">FAQ-Entwürfe mit KI erstellen</button> '
                . '<select class="tl_select seo-studio-faq-count" style="width:auto;display:inline-block">'
                . '<option value="3">3 Fragen</option><option value="5" selected>5 Fragen</option><option value="8">8 Fragen</option>'
                . '</select> '
                . '<span class="seo-studio-faq-status" aria-live="polite"></span></p>';
        }

        return '<div class="widget clr seo-studio-panel" data-seo-studio-meta'
            . ' data-page-id="' . $pageId . '"'
            . ' data-token="' . htmlspecialchars($token, ENT_QUOTES) . '"'
            . ' data-url="' . htmlspecialchars('/contao/seostudio/meta/generate', ENT_QUOTES) . '">'
            . '<h3>SEO Studio</h3>'
            . $metaSection
            . $faqSection
            . '</div>';
    }
}
