<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;

/**
 * Renders the inline panel markup used below a DCA field. One shared JS file
 * (panel.js) drives every adapter.
 */
final class PanelRenderer
{
    public function __construct(
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function render(string $adapterId, string $table, int $rowId, string $targetFieldId): string
    {
        $GLOBALS['TL_JAVASCRIPT']['seo_studio_panel'] = 'bundles/vtinnovationsseostudio/panel.js';
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<div class="widget clr seo-studio-panel seo-studio-inline" data-seo-studio-panel'
            . ' data-adapter="' . $e($adapterId) . '"'
            . ' data-table="' . $e($table) . '"'
            . ' data-row-id="' . $rowId . '"'
            . ' data-target="' . $e($targetFieldId) . '"'
            . ' data-token="' . $e($this->csrfTokenManager->getDefaultTokenValue()) . '"'
            . ' data-url="/contao/seostudio/panel/suggest">'
            . '<p><button type="button" class="tl_submit seo-studio-check">SEO Studio: KI-Check</button> '
            . '<span class="seo-studio-verdict"></span> '
            . '<span class="seo-studio-status" aria-live="polite"></span></p>'
            . '<div class="seo-studio-result" hidden>'
            . '<p class="seo-studio-reason"></p>'
            . '<ul class="seo-studio-alternatives"></ul>'
            . '</div></div>';
    }
}
