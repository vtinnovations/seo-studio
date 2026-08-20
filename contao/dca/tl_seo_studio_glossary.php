<?php

declare(strict_types=1);

use Contao\DataContainer;
use Contao\DC_Table;

/**
 * Glossary terms with AI-generated, curated definitions. Rendered by the
 * "seo_studio_glossary" frontend module with DefinedTermSet JSON-LD.
 */
$GLOBALS['TL_DCA']['tl_seo_studio_glossary'] = [
    'config' => [
        'dataContainer' => DC_Table::class,
        'enableVersioning' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
                'alias' => 'unique',
                'published' => 'index',
            ],
        ],
    ],
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTED,
            'fields' => ['term'],
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'panelLayout' => 'filter;search,limit',
        ],
        'label' => [
            'fields' => ['term'],
            'format' => '%s',
        ],
        'operations' => [
            'edit' => ['href' => 'act=edit', 'icon' => 'edit.svg'],
            'delete' => [
                'href' => 'act=delete',
                'icon' => 'delete.svg',
                'attributes' => 'onclick="if(!confirm(\'Eintrag wirklich löschen?\'))return false;Backend.getScrollOffset()"',
            ],
            'toggle' => ['href' => 'act=toggle&amp;field=published', 'icon' => 'visible.svg'],
            'show' => ['href' => 'act=show', 'icon' => 'show.svg'],
        ],
    ],
    'palettes' => [
        'default' => '{term_legend},term,alias,definition;{seo_legend},metaTitle,metaDescription;{publish_legend},published',
    ],
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'sql' => "int(10) unsigned NOT NULL default 0",
        ],
        'term' => [
            'inputType' => 'text',
            'exclude' => true,
            'search' => true,
            'sorting' => true,
            'flag' => DataContainer::SORT_INITIAL_LETTER_ASC,
            'eval' => ['mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'alias' => [
            'inputType' => 'text',
            'exclude' => true,
            'search' => true,
            'eval' => ['rgxp' => 'alias', 'doNotCopy' => true, 'unique' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => "varchar(255) BINARY NOT NULL default ''",
        ],
        'definition' => [
            'inputType' => 'textarea',
            'exclude' => true,
            'search' => true,
            'eval' => ['mandatory' => true, 'rte' => 'tinyMCE', 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'metaTitle' => [
            'inputType' => 'text',
            'exclude' => true,
            'search' => true,
            'eval' => ['maxlength' => 255, 'decodeEntities' => true, 'tl_class' => 'w50'],
            'sql' => "varchar(255) NOT NULL default ''",
        ],
        'metaDescription' => [
            'inputType' => 'textarea',
            'exclude' => true,
            'search' => true,
            'eval' => ['style' => 'height:60px', 'decodeEntities' => true, 'tl_class' => 'clr'],
            'sql' => 'text NULL',
        ],
        'published' => [
            'inputType' => 'checkbox',
            'exclude' => true,
            'filter' => true,
            'toggle' => true,
            'eval' => ['doNotCopy' => true],
            'sql' => "char(1) NOT NULL default ''",
        ],
    ],
];
