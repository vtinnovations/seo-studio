<?php

declare(strict_types=1);

/**
 * Replay/idempotency journal for vendor-initiated provisioning updates.
 * Written and pruned by the exchange journal only — never listed or edited in
 * the backend.
 *
 * The unique index on requestId is what makes the idempotency claim atomic
 * across parallel requests and multiple web nodes: the second INSERT loses.
 * Only digests are stored, never a nonce, key or payload.
 */
$GLOBALS['TL_DCA']['tl_seo_studio_exchange'] = [
    'config' => [
        'dataContainer' => \Contao\DC_Table::class,
        'closed' => true,
        'notEditable' => true,
        'notCopyable' => true,
        'notDeletable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'requestId' => 'unique',
                'nonceDigest' => 'index',
                'tstamp' => 'index',
            ],
        ],
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'requestId' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'nonceDigest' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'bodyDigest' => [
            'sql' => "varchar(64) NOT NULL default ''",
        ],
        'processedAt' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'result' => [
            'sql' => "varchar(32) NOT NULL default ''",
        ],
        'appliedVersion' => [
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
    ],
];
