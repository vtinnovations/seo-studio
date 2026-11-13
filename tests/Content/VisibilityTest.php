<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Content;

use PHPUnit\Framework\TestCase;
use VTinnovations\SeoStudio\Core\Content\Visibility;

/**
 * The checks must see exactly what a visitor sees: the same rules Contao's
 * frontend applies (ContentModel / ArticleModel "published" queries).
 */
final class VisibilityTest extends TestCase
{
    private const NOW = 1784880547;

    public function testContentExcludesHiddenAndScheduledOutElements(): void
    {
        self::assertSame(
            "invisible = 0 AND (start = '' OR start <= 1784880547) AND (stop = '' OR stop > 1784880547)",
            Visibility::content('', self::NOW),
        );
    }

    public function testArticleRequiresPublishedAndTheScheduleWindow(): void
    {
        self::assertSame(
            "published = 1 AND (start = '' OR start <= 1784880547) AND (stop = '' OR stop > 1784880547)",
            Visibility::article('', self::NOW),
        );
    }

    public function testAliasIsAppliedToEveryColumn(): void
    {
        self::assertSame(
            "c.invisible = 0 AND (c.start = '' OR c.start <= 1784880547) AND (c.stop = '' OR c.stop > 1784880547)",
            Visibility::content('c', self::NOW),
        );
        self::assertSame(
            "a.published = 1 AND (a.start = '' OR a.start <= 1784880547) AND (a.stop = '' OR a.stop > 1784880547)",
            Visibility::article('a', self::NOW),
        );
    }

    public function testDefaultsToTheCurrentTime(): void
    {
        $before = time();
        $sql = Visibility::content();
        $after = time();

        self::assertMatchesRegularExpression('/start <= (\d+)\)/', $sql);
        preg_match('/start <= (\d+)\)/', $sql, $m);
        self::assertGreaterThanOrEqual($before, (int) $m[1]);
        self::assertLessThanOrEqual($after, (int) $m[1]);
    }
}
