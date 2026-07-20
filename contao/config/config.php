<?php

declare(strict_types=1);

use VTinnovations\SeoStudio\Controller\AuditModule;
use VTinnovations\SeoStudio\Controller\DashboardModule;
use VTinnovations\SeoStudio\Controller\GenerateModule;
use VTinnovations\SeoStudio\Controller\SettingsModule;

/*
 * Backend module group "SEO Studio", ordered by workflow:
 *   Übersicht → Inhalte erzeugen → Analysieren → Kuratieren (FAQ/Glossar) → Einstellungen.
 * Each callback checks FeatureState and renders a notice when disabled.
 */
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
