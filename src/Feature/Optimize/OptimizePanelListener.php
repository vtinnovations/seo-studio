<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Optimize;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Scans the content tables and injects an "SEO optimieren" panel below every
 * headline-like and text-like field — globally, so any content element
 * (including page-builder bundles like Draggo, which store their elements in
 * tl_content) gets the button without per-field wiring.
 *
 * Scope is deliberately limited to the editorial tables so the backend isn't
 * flooded with buttons on config/module/layout DCAs.
 */
#[AsHook('loadDataContainer')]
final class OptimizePanelListener
{
    private const TABLES = ['tl_content', 'tl_news', 'tl_calendar_events'];

    /** Field NAMES treated as a headline regardless of inputType. */
    private const HEADLINE_NAMES = ['headline', 'subheadline', 'title', 'subtitle', 'ueberschrift'];

    /** Field names to never touch (aliases, internal). */
    private const SKIP_NAMES = ['alias', 'cssID', 'guests', 'jumpTo'];

    public function __construct(
        private readonly FeatureState $featureState,
        private readonly ContaoCsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function __invoke(string $table): void
    {
        if (!\in_array($table, self::TABLES, true) || !$this->featureState->isEnabled('optimize')) {
            return;
        }

        if (!isset($GLOBALS['TL_DCA'][$table]['fields']) || !\is_array($GLOBALS['TL_DCA'][$table]['fields'])) {
            return;
        }

        foreach ($GLOBALS['TL_DCA'][$table]['fields'] as $fieldName => $config) {
            if (!\is_string($fieldName) || !\is_array($config) || \in_array($fieldName, self::SKIP_NAMES, true)) {
                continue;
            }

            $fieldType = $this->classify($fieldName, $config);
            if ($fieldType === null) {
                continue;
            }

            $panelName = 'seoStudioOpt_' . $fieldName;
            if (isset($GLOBALS['TL_DCA'][$table]['fields'][$panelName])) {
                continue;
            }

            $this->registerPanel($table, $panelName, $fieldName, $fieldType);
            $this->injectIntoPalettes($table, $panelName, $fieldName);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return 'headline'|'text'|null
     */
    private function classify(string $fieldName, array $config): ?string
    {
        $inputType = (string) ($config['inputType'] ?? '');
        $lower = strtolower($fieldName);

        // Headline: Contao's inputUnit widget, or a headline-ish field name.
        if ($inputType === 'inputUnit' || \in_array($lower, self::HEADLINE_NAMES, true)) {
            return 'headline';
        }

        // Text: any textarea (RTE or plain) that stores real copy.
        if ($inputType === 'textarea') {
            return 'text';
        }

        return null;
    }

    private function registerPanel(string $table, string $panelName, string $targetField, string $fieldType): void
    {
        $GLOBALS['TL_DCA'][$table]['fields'][$panelName] = [
            'input_field_callback' => fn (DataContainer $dc): string => $this->render($table, (int) $dc->id, $targetField, $fieldType),
            'eval' => ['doNotSaveEmpty' => true],
        ];
    }

    private function render(string $table, int $rowId, string $targetField, string $fieldType): string
    {
        $GLOBALS['TL_JAVASCRIPT']['seo_studio_optimize'] = 'bundles/vtinnovationsseostudio/optimize.js';
        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        $e = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $label = $fieldType === 'headline' ? 'Überschrift' : 'Text';

        return '<div class="widget clr seo-studio-panel seo-studio-optimize" data-seo-studio-optimize'
            . ' data-table="' . $e($table) . '"'
            . ' data-row-id="' . $rowId . '"'
            . ' data-field="' . $e($targetField) . '"'
            . ' data-field-type="' . $e($fieldType) . '"'
            . ' data-target="ctrl_' . $e($targetField) . '"'
            . ' data-token="' . $e($this->csrfTokenManager->getDefaultTokenValue()) . '"'
            . ' data-url="/contao/seostudio/optimize">'
            . '<p><button type="button" class="tl_submit seo-studio-opt-check">SEO-Check (' . $e($label) . ')</button> '
            . '<button type="button" class="tl_submit seo-studio-opt-rewrite">Mit KI optimieren</button> '
            . '<span class="seo-studio-verdict"></span> '
            . '<span class="seo-studio-status" aria-live="polite"></span></p>'
            . '<div class="seo-studio-result" hidden>'
            . '<p class="seo-studio-reason"></p>'
            . '<div class="seo-studio-rewrite-box" hidden>'
            . '<p class="seo-studio-muted">Vorschlag:</p>'
            . '<div class="seo-studio-note seo-studio-rewrite-preview"></div>'
            . '<p><button type="button" class="tl_submit seo-studio-opt-apply">In Feld übernehmen</button> '
            . '<button type="button" class="tl_submit seo-studio-opt-discard">Verwerfen</button></p>'
            . '</div>'
            . '<ul class="seo-studio-alternatives"></ul>'
            . '</div></div>';
    }

    private function injectIntoPalettes(string $table, string $panelName, string $afterField): void
    {
        // Main palettes.
        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $name => $palette) {
            if ($name === '__selector__' || !\is_string($palette)) {
                continue;
            }
            if (!$this->paletteHasField($palette, $afterField) || str_contains($palette, $panelName)) {
                continue;
            }

            PaletteManipulator::create()
                ->addField($panelName, $afterField, PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($name, $table);
        }

        // Subpalettes (e.g. text lives in a selector subpalette on some elements).
        foreach ($GLOBALS['TL_DCA'][$table]['subpalettes'] ?? [] as $selector => $palette) {
            if (!\is_string($palette) || !$this->paletteHasField($palette, $afterField) || str_contains($palette, $panelName)) {
                continue;
            }

            PaletteManipulator::create()
                ->addField($panelName, $afterField, PaletteManipulator::POSITION_AFTER)
                ->applyToSubpalette((string) $selector, $table);
        }
    }

    private function paletteHasField(string $palette, string $field): bool
    {
        return (bool) preg_match('/(^|[,;])' . preg_quote($field, '/') . '([,;]|$)/', $palette);
    }
}
