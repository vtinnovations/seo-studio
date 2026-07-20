<?php

declare(strict_types=1);

$GLOBALS['TL_LANG']['SEO_STUDIO']['saved'] = 'Settings saved.';
$GLOBALS['TL_LANG']['SEO_STUDIO']['budgetStatus'] = 'Token usage this month: %s of %s (%d%%)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['budgetStatusUnlimited'] = 'Token usage this month: %s (no limit set)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['tierLocked'] = 'higher plan required';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendAi'] = 'AI settings';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiProvider'] = 'AI provider';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiModel'] = 'Model (empty = default)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiApiKey'] = 'API key';
$GLOBALS['TL_LANG']['SEO_STUDIO']['keySet'] = 'stored — leave empty to keep, "!delete" to remove';
$GLOBALS['TL_LANG']['SEO_STUDIO']['keyEmpty'] = 'no key stored yet';
$GLOBALS['TL_LANG']['SEO_STUDIO']['aiBaseUrl'] = 'Base URL (only for "compatible")';
$GLOBALS['TL_LANG']['SEO_STUDIO']['monthlyTokenBudget'] = 'Monthly token budget (0 = unlimited)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['noExternalCalls'] = 'No external calls (disable AI entirely)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendFeatures'] = 'Features';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendBehavior'] = 'Behavior';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeMode'] = 'Write mode';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeModePropose'] = 'Propose → preview → apply';
$GLOBALS['TL_LANG']['SEO_STUDIO']['writeModeFillEmpty'] = 'Fill empty fields only';
$GLOBALS['TL_LANG']['SEO_STUDIO']['cronBatchSize'] = 'Cron batch size';
$GLOBALS['TL_LANG']['SEO_STUDIO']['languageOverride'] = 'Language override (empty = root page language)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['metaCronEnabled'] = 'Cron: auto-fill empty titles/descriptions (consumes tokens)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['legendSchema'] = 'Structured data (Schema.org)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgName'] = 'Organization: name';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgLogo'] = 'Organization: logo URL';
$GLOBALS['TL_LANG']['SEO_STUDIO']['schemaOrgSameAs'] = 'Organization: profiles (sameAs, one URL per line)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llmsTxtSummary'] = 'llms.txt: short site description';
$GLOBALS['TL_LANG']['SEO_STUDIO']['llmsSummaryGenerate'] = 'Generate with AI';
$GLOBALS['TL_LANG']['SEO_STUDIO']['save'] = 'Save';
$GLOBALS['TL_LANG']['SEO_STUDIO']['testConnection'] = 'Test connection';

$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['meta'] = 'Meta generation (page title + description)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['audit'] = 'Audits (robots.txt AI crawlers, structure)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['schema'] = 'Structured data (JSON-LD: Organization, Breadcrumb, Article)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['llmsTxt'] = 'llms.txt (machine-readable site overview for AI agents)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['freshness'] = 'Freshness (dateModified in schema + sitemap lastmod)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['faq'] = 'FAQ generation + FAQPage schema (frontend module)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['optimize'] = 'Text optimization (headlines + text blocks: check/rewrite/generate)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['inlineAltText'] = 'Inline check: alt texts (vision)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['inlineLinkText'] = 'Inline check: link texts';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['images'] = 'Image audit + optimization wizard';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['geoScore'] = 'SEO/GEO/AEO score (per-page visibility maturity)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['pageScore'] = 'Per-page SEO score (focus keyword, checklist, traffic light in page list)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['social'] = 'Social media preview (Open Graph + Twitter/X cards, image + live preview)';
$GLOBALS['TL_LANG']['SEO_STUDIO']['features']['glossary'] = 'AI glossary (terms + definitions, frontend module, schema)';
