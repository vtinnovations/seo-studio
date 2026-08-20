<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Feature\InlinePanel;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Injects the two SPECIALISED inline panels on tl_content:
 *
 *   - tl_content.alt (subpalette)   (adapter "altText" — Vision)
 *   - tl_content.linkTitle          (adapter "linkText" — deterministic)
 *
 * Headlines and text blocks are handled globally by the Optimize panel
 * (score + rewrite + generate). Disabled adapter = zero backend trace.
 */
#[AsHook('loadDataContainer')]
final class PanelDcaListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly PanelRenderer $renderer,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ($table === 'tl_content') {
            $this->injectContentPanels();
        }
    }

    private function injectContentPanels(): void
    {
        // Headlines are handled by the global Optimize panel (score + rewrite
        // + generate). This listener only keeps the two specialised panels:
        // altText (Vision) and linkText (deterministic).
        if ($this->featureState->isEnabled('inlineLinkText')) {
            $this->registerField('tl_content', 'seoStudioLinkPanel', 'linkText', 'ctrl_linkTitle');
            $this->injectIntoPalettes('tl_content', 'seoStudioLinkPanel', 'linkTitle');
        }

        if ($this->featureState->isEnabled('inlineAltText')) {
            $this->registerField('tl_content', 'seoStudioAltPanel', 'altText', 'ctrl_alt');

            // alt lives in the overwriteMeta SUBpalette of image-type elements.
            $subpalette = (string) ($GLOBALS['TL_DCA']['tl_content']['subpalettes']['overwriteMeta'] ?? '');
            if ($subpalette !== '' && str_contains($subpalette, 'alt') && !str_contains($subpalette, 'seoStudioAltPanel')) {
                PaletteManipulator::create()
                    ->addField('seoStudioAltPanel', 'alt', PaletteManipulator::POSITION_AFTER)
                    ->applyToSubpalette('overwriteMeta', 'tl_content');
            }
        }
    }

    private function registerField(string $table, string $fieldName, string $adapterId, string $targetFieldId): void
    {
        $GLOBALS['TL_DCA'][$table]['fields'][$fieldName] = [
            'input_field_callback' => fn (DataContainer $dc): string => $this->renderer->render(
                $adapterId,
                $table,
                (int) $dc->id,
                $targetFieldId,
            ),
            'eval' => ['doNotSaveEmpty' => true],
        ];
    }

    private function injectIntoPalettes(string $table, string $fieldName, string $afterField): void
    {
        foreach ($GLOBALS['TL_DCA'][$table]['palettes'] ?? [] as $name => $palette) {
            if ($name === '__selector__' || !\is_string($palette)) {
                continue;
            }

            if (!preg_match('/[,;]' . $afterField . '[,;]|^' . $afterField . '[,;]|[,;]' . $afterField . '$/', $palette)) {
                continue;
            }

            if (str_contains($palette, $fieldName)) {
                continue;
            }

            PaletteManipulator::create()
                ->addField($fieldName, $afterField, PaletteManipulator::POSITION_AFTER)
                ->applyToPalette($name, $table);
        }
    }
}
