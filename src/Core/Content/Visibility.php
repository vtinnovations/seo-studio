<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Content;

/**
 * SQL conditions for "a visitor sees this right now", identical to the rules
 * Contao's own frontend applies (ContentModel::findPublishedByPidAndTable,
 * ArticleModel::findPublishedByPidAndColumn):
 *
 *   - content element: not hidden, and inside its "show from / show until" window;
 *   - article: published, and inside its "show from / show until" window.
 *
 * Every deterministic check must use these, so that content an editor has
 * switched off — by the visibility toggle or by scheduling — never counts
 * (e.g. two alternative H1 elements of which only one is shown at a time).
 *
 * The timestamp is inlined as an integer (never user input), exactly as Contao
 * does, so the fragments work inside queries with positional parameters.
 */
final class Visibility
{
    /**
     * @param string   $alias table alias ('' for an unaliased tl_content)
     * @param int|null $now   Unix time to evaluate against (default: now)
     */
    public static function content(string $alias = '', ?int $now = null): string
    {
        $p = $alias !== '' ? $alias . '.' : '';

        return "{$p}invisible = 0 AND " . self::window($p, $now ?? time());
    }

    /**
     * @param string   $alias table alias ('' for an unaliased tl_article)
     * @param int|null $now   Unix time to evaluate against (default: now)
     */
    public static function article(string $alias = '', ?int $now = null): string
    {
        $p = $alias !== '' ? $alias . '.' : '';

        return "{$p}published = 1 AND " . self::window($p, $now ?? time());
    }

    private static function window(string $p, int $now): string
    {
        return "({$p}start = '' OR {$p}start <= {$now}) AND ({$p}stop = '' OR {$p}stop > {$now})";
    }
}
