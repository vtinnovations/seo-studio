<?php

declare(strict_types=1);

/**
 * Licence section inside Contao → Settings.
 *
 * This is the ONE administrator-facing licence surface of the bundle: there is
 * no separate backend module, no root-page panel and no second settings screen.
 *
 * The field renders through a Contao backend widget and deliberately never
 * submits a value of its own (the widget keeps $blnSubmitInput = false), so
 * nothing licence-related is written into localconfig.php. The authoritative
 * state lives in var/seostudio/provisioning as exact vendor-issued bytes plus
 * the signed envelope that seals them; the actions are handled by the
 * tl_settings onsubmit callback.
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/*
 * The panel's styles live in the bundle's backend stylesheet. Registered while
 * the DCA loads — that is before the settings form is rendered, so the section
 * is never painted unstyled.
 */
$GLOBALS['TL_CSS']['seo_studio'] = 'bundles/vtinnovationsseostudio/backend.css';

$GLOBALS['TL_DCA']['tl_settings']['fields']['vtoneSeoStudioLicence'] = [
    'label' => &$GLOBALS['TL_LANG']['tl_settings']['vtoneSeoStudioLicence'],
    'inputType' => 'seoStudioProvisioningPanel',
    'eval' => ['tl_class' => 'clr long', 'doNotSaveEmpty' => true],
];

// Shared legend: every V-T.ONE package adds its field to the same
// "vtone_licence_legend" group, so all licence sections sit together in one
// fieldset at the top of the Settings screen, above Contao's own legends,
// with the field's own label (the package name) as its heading — Contao's
// stock widget template renders that label as an <h3> above generate().
PaletteManipulator::create()
    ->addLegend('vtone_licence_legend', null, PaletteManipulator::POSITION_PREPEND)
    ->addField('vtoneSeoStudioLicence', 'vtone_licence_legend', PaletteManipulator::POSITION_APPEND)
    ->applyToPalette('default', 'tl_settings')
;
