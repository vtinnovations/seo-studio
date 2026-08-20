<?php

declare(strict_types=1);

/**
 * LLM verdict cache for inline panels, keyed by content hash. Managed by
 * VerdictCache — no backend listing.
 */
$GLOBALS['TL_DCA']['tl_seo_studio_verdict'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'cacheKey' => 'unique',
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
        'cacheKey' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'payload' => [
            'sql' => 'text NULL',
        ],
    ],
];
