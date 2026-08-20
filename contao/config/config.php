<?php

declare(strict_types=1);

use Contao\System;
use VTinnovations\SeoStudio\Controller\AuditModule;
use VTinnovations\SeoStudio\Controller\DashboardModule;
use VTinnovations\SeoStudio\Controller\GenerateModule;
use VTinnovations\SeoStudio\Controller\SettingsModule;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\InstanceStatePanel;

/*
 * The licence section widget for Contao → Settings. Registered unconditionally:
 * an administrator must always be able to reach the licence surface, including
 * when nothing is licensed.
 */
$GLOBALS['BE_FFL']['seoStudioProvisioningPanel'] = InstanceStatePanel::class;

/*
 * Backend module group "SEO Studio", ordered by workflow:
 *   Übersicht → Inhalte erzeugen → Analysieren → Kuratieren (FAQ/Glossar) → Einstellungen.
 * Each callback checks FeatureState and renders a notice when disabled.
 *
 * The group is registered only while the instance holds a valid licence. Without
 * one, Contao looks exactly as it does without this bundle installed — no menu
 * group, no modules — while every page, FAQ entry and glossary entry stays in
 * the database untouched. Licence management itself lives in Contao → Settings
 * and is always reachable.
 *
 * Any failure while evaluating (missing container, unmigrated database) fails
 * closed for the same reason.
 */
$seoStudioLicensed = false;

try {
    $seoStudioContainer = System::getContainer();

    if ($seoStudioContainer !== null && $seoStudioContainer->has(EntitlementEvaluator::class)) {
        /** @var EntitlementEvaluator $seoStudioEntitlement */
        $seoStudioEntitlement = $seoStudioContainer->get(EntitlementEvaluator::class);
        $seoStudioLicensed = $seoStudioEntitlement->isLicensed();
    }
} catch (Throwable) {
    $seoStudioLicensed = false;
}

if ($seoStudioLicensed) {
    $GLOBALS['BE_MOD']['seo_studio'] = [
        'seo_dashboard' => [
            'callback' => DashboardModule::class,
        ],
        'seo_generate' => [
            'callback' => GenerateModule::class,
        ],
        'seo_analyse' => [
            'callback' => AuditModule::class,
        ],
        'seo_faq' => [
            'tables' => ['tl_seo_studio_faq'],
        ],
        'seo_glossary' => [
            'tables' => ['tl_seo_studio_glossary'],
        ],
        'seo_settings' => [
            'callback' => SettingsModule::class,
        ],
    ];
}

unset($seoStudioContainer, $seoStudioEntitlement, $seoStudioLicensed);
