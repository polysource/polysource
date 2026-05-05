<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Job;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;

final class BulkJobTest extends TestCase
{
    public function testHappyPathBuildsImmutableJob(): void
    {
        $job = $this->makeJob();

        self::assertSame('01HF000000000000000000ABCD', $job->id);
        self::assertSame('orders', $job->resourceName);
        self::assertSame('retry-all', $job->actionName);
        self::assertSame('alice', $job->actorId);
        self::assertSame(['ord-1', 'ord-2', 'ord-3'], $job->recordIds);
        self::assertSame(BulkJobStatus::Pending, $job->status);
        self::assertSame(3, $job->total());
        self::assertSame(0.0, $job->progress());
    }

    public function testProgressIsRatio(): void
    {
        $job = $this->makeJob()->withProgress(2, 0);
        self::assertEqualsWithDelta(2 / 3, $job->progress(), 0.0001);
    }

    public function testEmptyRecordIdsImpliesProgressOne(): void
    {
        $job = new BulkJob(
            id: '01HF000000000000000000EMPT',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: [],
            status: BulkJobStatus::Completed,
        );
        self::assertSame(0, $job->total());
        self::assertSame(1.0, $job->progress());
    }

    public function testWithStatusReturnsNewInstance(): void
    {
        $job = $this->makeJob();
        $running = $job->withStatus(BulkJobStatus::Running);

        self::assertSame(BulkJobStatus::Pending, $job->status);
        self::assertSame(BulkJobStatus::Running, $running->status);
        self::assertNotSame($job, $running);
    }

    public function testWithProgressUpdatesCounts(): void
    {
        $job = $this->makeJob()->withProgress(2, 1);
        self::assertSame(2, $job->processedCount);
        self::assertSame(1, $job->failedCount);
    }

    public function testEmptyIdRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BulkJob(
            id: '',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'r',
            actionName: 'a',
            actorId: 'u',
            recordIds: [],
            status: BulkJobStatus::Pending,
        );
    }

    public function testProcessedCountCannotExceedTotal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BulkJob(
            id: 'id',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'r',
            actionName: 'a',
            actorId: 'u',
            recordIds: ['rec-1'],
            status: BulkJobStatus::Running,
            processedCount: 5,
        );
    }

    public function testNonListRecordIdsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BulkJob(
            id: 'id',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'r',
            actionName: 'a',
            actorId: 'u',
            // @phpstan-ignore-next-line argument.type — exercising runtime guard
            recordIds: ['key' => 'value'],
            status: BulkJobStatus::Pending,
        );
    }

    public function testNonUtcDateNormalisedToUtc(): void
    {
        $paris = new DateTimeImmutable('2026-05-05T12:00:00', new DateTimeZone('Europe/Paris'));
        $job = new BulkJob(
            id: 'id',
            createdAt: $paris,
            resourceName: 'r',
            actionName: 'a',
            actorId: 'u',
            recordIds: [],
            status: BulkJobStatus::Pending,
        );

        self::assertSame('UTC', $job->createdAt->getTimezone()->getName());
        self::assertSame('2026-05-05T10:00:00+00:00', $job->createdAt->format(\DATE_ATOM));
    }

    public function testStatusEnumIsTerminal(): void
    {
        self::assertFalse(BulkJobStatus::Pending->isTerminal());
        self::assertFalse(BulkJobStatus::Running->isTerminal());
        self::assertTrue(BulkJobStatus::Completed->isTerminal());
        self::assertTrue(BulkJobStatus::Failed->isTerminal());
        self::assertTrue(BulkJobStatus::Cancelled->isTerminal());
    }

    private function makeJob(): BulkJob
    {
        return new BulkJob(
            id: '01HF000000000000000000ABCD',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: ['ord-1', 'ord-2', 'ord-3'],
            status: BulkJobStatus::Pending,
        );
    }
}
