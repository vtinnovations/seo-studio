<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\PageScore;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Wires the per-page SEO score into tl_page:
 *   - a focus-keyword text field,
 *   - a checklist panel below it (traffic light + grouped checks),
 *   - a colour score badge on every regular page row in the page tree.
 *
 * Zero trace when the feature is disabled.
 */
#[AsHook('loadDataContainer')]
final class PageScoreDcaListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly PageSeoAnalyzer $analyzer,
        private readonly SeoChecklistRenderer $renderer,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ($table !== 'tl_page' || !$this->featureState->isEnabled('pageScore')) {
            return;
        }

        // Load the badge/checklist styles on every tl_page view (tree + edit form).
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        // Focus keyword field (+ SQL column → auto-migrated).
        $GLOBALS['TL_DCA']['tl_page']['fields']['seoFocusKeyword'] = [
            'inputType' => 'text',
            'exclude' => true,
            'search' => true,
            'eval' => ['maxlength' => 128, 'tl_class' => 'w50', 'decodeEntities' => true],
            'sql' => "varchar(128) NOT NULL default ''",
        ];

        // Virtual checklist panel.
        $GLOBALS['TL_DCA']['tl_page']['fields']['seoScorePanel'] = [
            'input_field_callback' => fn (DataContainer $dc): string => $this->renderer->render((int) $dc->id),
            'eval' => ['doNotSaveEmpty' => true],
        ];

        foreach (['regular', 'forward'] as $palette) {
            if (!isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])
                || !str_contains((string) $GLOBALS['TL_DCA']['tl_page']['palettes'][$palette], 'description')) {
                continue;
            }

            PaletteManipulator::create()
                ->addField(['seoFocusKeyword', 'seoScorePanel'], 'description', PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($palette, 'tl_page');
        }

        $this->injectTreeBadge();
    }

    /**
     * Wraps tl_page's label_callback (the tree row renderer, core ['tl_page','addIcon'])
     * to append a colour score badge on regular/forward pages.
     */
    private function injectTreeBadge(): void
    {
        $label = &$GLOBALS['TL_DCA']['tl_page']['list']['label'];
        $previous = $label['label_callback'] ?? null;
        $analyzer = $this->analyzer;

        $label['label_callback'] = static function (array $row, string $rowLabel, ?DataContainer $dc = null, string $imageAttribute = '', bool $blnReturnImage = false, bool $blnProtected = false, bool $isVisibleRootTrailPage = false) use ($previous, $analyzer): string {
            // Render the original label first (core addIcon or another extension's callback).
            if (\is_array($previous) && \count($previous) === 2) {
                $rowLabel = (string) \Contao\System::importStatic($previous[0])->{$previous[1]}($row, $rowLabel, $dc, $imageAttribute, $blnReturnImage, $blnProtected, $isVisibleRootTrailPage);
            } elseif (\is_callable($previous)) {
                $rowLabel = (string) $previous($row, $rowLabel, $dc, $imageAttribute, $blnReturnImage, $blnProtected, $isVisibleRootTrailPage);
            }

            // $blnReturnImage → core wants only the tree icon; never decorate that.
            if ($blnReturnImage || !\in_array((string) ($row['type'] ?? ''), ['regular', 'forward'], true)) {
                return $rowLabel;
            }

            try {
                $report = $analyzer->analyze((int) $row['id']);
            } catch (\Throwable) {
                return $rowLabel;
            }

            $badge = '<span class="seo-studio-pagebadge seo-studio-pagebadge--' . $report->color() . '" '
                . 'title="SEO-Score ' . $report->score . '/100'
                . ($report->problemCount() > 0 ? ', ' . $report->problemCount() . ' Problem(e)' : '')
                . '">' . $report->score . '</span> ';

            return $badge . $rowLabel;
        };
    }
}
