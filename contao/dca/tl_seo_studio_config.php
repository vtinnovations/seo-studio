<?php

declare(strict_types=1);

/**
 * Key/value store for AI SEO Studio. Managed exclusively through the
 * settings backend module (ConfigProvider) — no DCA listing.
 */
$GLOBALS['TL_DCA']['tl_seo_studio_config'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'name' => 'unique',
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
        'name' => [
            'sql' => "varchar(190) NOT NULL default ''",
        ],
        'value' => [
            'sql' => 'text NULL',
        ],
    ],
];
