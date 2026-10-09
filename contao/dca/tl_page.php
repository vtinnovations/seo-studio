<?php

declare(strict_types=1);

/**
 * Storage columns for the page-level SEO Studio fields.
 *
 * Only the SQL definitions live here, unconditionally, so the database schema
 * never depends on the licence or a feature toggle: the columns are created on
 * every install and the schema diff never proposes dropping them (and the data
 * in them) when a feature is switched off. The editable side of each field —
 * inputType, eval, palette placement — is added by the feature's
 * loadDataContainer listener only while that feature is enabled
 * (PageScoreDcaListener, SocialDcaListener). Without an inputType and outside
 * every palette, these fields are not editable in the backend.
 */

// PageScore
$GLOBALS['TL_DCA']['tl_page']['fields']['seoFocusKeyword']['sql'] = "varchar(128) NOT NULL default ''";

// Social
$GLOBALS['TL_DCA']['tl_page']['fields']['seoSocialTitle']['sql'] = "varchar(255) NOT NULL default ''";
$GLOBALS['TL_DCA']['tl_page']['fields']['seoSocialDescription']['sql'] = "varchar(255) NOT NULL default ''";
$GLOBALS['TL_DCA']['tl_page']['fields']['seoOgImage']['sql'] = 'binary(16) NULL';
