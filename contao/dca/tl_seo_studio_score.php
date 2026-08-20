<?php

declare(strict_types=1);

/**
 * Per-page GEO readiness scores. Written by GeoScoreCalculator — no backend
 * listing (the dashboard renders them).
 */
$GLOBALS['TL_DCA']['tl_seo_studio_score'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'pageId' => 'unique',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'pageId' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'score' => [
            'sql' => "smallint(5) unsigned NOT NULL default 0",
        ],
        'components' => [
            'sql' => 'text NULL',
        ],
    ],
];
