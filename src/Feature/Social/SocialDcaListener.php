<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Feature\Social;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\DataContainer;
use VTinnovations\SeoStudio\Core\Config\FeatureState;

/**
 * Wires the social-media fields + live preview into tl_page:
 *   - optional social title/description overrides,
 *   - an Open Graph image picker,
 *   - a Facebook/LinkedIn/X-style preview card.
 *
 * Zero trace when the feature is disabled.
 */
#[AsHook('loadDataContainer')]
final class SocialDcaListener
{
    public function __construct(
        private readonly FeatureState $featureState,
        private readonly SocialPreviewRenderer $renderer,
    ) {
    }

    public function __invoke(string $table): void
    {
        if ($table !== 'tl_page' || !$this->featureState->isEnabled('social')) {
            return;
        }

        $GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

        $GLOBALS['TL_DCA']['tl_page']['fields']['seoSocialTitle'] = [
            'inputType' => 'text',
            'exclude' => true,
            'eval' => ['maxlength' => 90, 'tl_class' => 'w50', 'decodeEntities' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ];

        $GLOBALS['TL_DCA']['tl_page']['fields']['seoSocialDescription'] = [
            'inputType' => 'text',
            'exclude' => true,
            'eval' => ['maxlength' => 200, 'tl_class' => 'w50', 'decodeEntities' => true],
            'sql' => "varchar(255) NOT NULL default ''",
        ];

        $GLOBALS['TL_DCA']['tl_page']['fields']['seoOgImage'] = [
            'inputType' => 'fileTree',
            'exclude' => true,
            'eval' => [
                'filesOnly' => true,
                'fieldType' => 'radio',
                'extensions' => 'jpg,jpeg,png,webp,gif',
                'tl_class' => 'clr',
            ],
            'sql' => 'binary(16) NULL',
        ];

        $GLOBALS['TL_DCA']['tl_page']['fields']['seoSocialPreview'] = [
            'input_field_callback' => fn (DataContainer $dc): string => $this->renderer->render((int) $dc->id),
            'eval' => ['doNotSaveEmpty' => true],
        ];

        foreach (['regular', 'forward'] as $palette) {
            if (!isset($GLOBALS['TL_DCA']['tl_page']['palettes'][$palette])
                || !str_contains((string) $GLOBALS['TL_DCA']['tl_page']['palettes'][$palette], 'description')) {
                continue;
            }

            PaletteManipulator::create()
                ->addField(
                    ['seoSocialTitle', 'seoSocialDescription', 'seoOgImage', 'seoSocialPreview'],
                    'description',
                    PaletteManipulator::POSITION_AFTER,
                )
                ->applyToPalette($palette, 'tl_page');
        }
    }
}
