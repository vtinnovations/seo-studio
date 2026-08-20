<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Exchange;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use VTinnovations\SeoStudio\Exchange\Journal;

/**
 * Replay/idempotency semantics of the exchange journal.
 */
final class JournalTest extends TestCase
{
    private const NOW = 1784880547;

    public function testANewRequestIsClaimed(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects(self::once())->method('insert')->willReturn(1);

        $journal = new Journal($connection);

        self::assertSame(Journal::CLAIMED, $journal->claim('req-1', 'nonce-digest', 'body-digest', self::NOW));
    }

    public function testAnExactRetryReportsTheEarlierResult(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('req-1');
        $connection->method('fetchAssociative')->willReturn(['bodyDigest' => 'body-digest', 'appliedVersion' => 9]);
        $connection->expects(self::never())->method('insert');

        $journal = new Journal($connection);

        self::assertSame(Journal::DUPLICATE_MATCH, $journal->claim('req-1', 'nonce-digest', 'body-digest', self::NOW));
    }

    public function testTheSameIdWithDifferentContentIsAConflict(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('req-1');
        $connection->method('fetchAssociative')->willReturn(['bodyDigest' => 'a-different-digest', 'appliedVersion' => 9]);
        $connection->expects(self::never())->method('insert');

        $journal = new Journal($connection);

        self::assertSame(Journal::DUPLICATE_CONFLICT, $journal->claim('req-1', 'nonce-digest', 'body-digest', self::NOW));
    }

    public function testANonceSeenUnderAnotherRequestIdIsAReplay(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn('some-other-request');
        $connection->expects(self::never())->method('insert');

        $journal = new Journal($connection);

        self::assertSame(Journal::NONCE_REPLAY, $journal->claim('req-1', 'nonce-digest', 'body-digest', self::NOW));
    }

    public function testLosingTheInsertRaceIsResolvedByComparingContent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(false);

        // First lookup: nothing there. After the failed insert the winner's row
        // exists, and its content decides between retry and conflict.
        $connection->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            false,
            ['bodyDigest' => 'body-digest'],
        );

        $connection->method('insert')->willThrowException(
            $this->createMock(UniqueConstraintViolationException::class),
        );

        $journal = new Journal($connection);

        self::assertSame(Journal::DUPLICATE_MATCH, $journal->claim('req-1', 'nonce-digest', 'body-digest', self::NOW));
    }

    public function testAnUnavailableJournalNeverReportsAClaim(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('table missing'));

        $journal = new Journal($connection);

        self::assertSame(Journal::UNAVAILABLE, $journal->claim('req-1', 'nonce-digest', 'body-digest', self::NOW));
    }

    public function testCompletionFailureDoesNotBubbleUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('update')->willThrowException(new \RuntimeException('gone'));

        $this->expectNotToPerformAssertions();

        (new Journal($connection))->complete('req-1', 'updated', 9, self::NOW);
    }

    public function testPruningIsBounded(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('DELETE FROM tl_seo_studio_exchange'),
                self::callback(static fn (array $parameters): bool => $parameters[0] < self::NOW),
            )
            ->willReturn(0);

        (new Journal($connection))->prune(self::NOW);
    }
}
