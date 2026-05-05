<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Action;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Action\CancelBulkJobAction;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Tests\InMemory\InMemoryBulkJobStorage;
use Polysource\Core\Query\DataRecord;

final class CancelBulkJobActionTest extends TestCase
{
    public function testFlipsRunningJobToCancelled(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $job = $this->makeJob(BulkJobStatus::Running);
        $storage->save($job);

        $action = new CancelBulkJobAction($storage);
        $result = $action->execute(new DataRecord($job->id, ['status' => $job->status->value]));

        self::assertTrue($result->success);
        $stored = $storage->find($job->id);
        self::assertInstanceOf(BulkJob::class, $stored);
        self::assertSame(BulkJobStatus::Cancelled, $stored->status);
    }

    public function testIdempotentOnTerminalJob(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $job = $this->makeJob(BulkJobStatus::Completed);
        $storage->save($job);

        $result = (new CancelBulkJobAction($storage))->execute(new DataRecord($job->id, ['status' => $job->status->value]));

        self::assertTrue($result->success);
        $stored = $storage->find($job->id);
        self::assertInstanceOf(BulkJob::class, $stored);
        self::assertSame(BulkJobStatus::Completed, $stored->status, 'Already-terminal job must not flip.');
    }

    public function testReturnsFailureWhenJobMissing(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $result = (new CancelBulkJobAction($storage))->execute(new DataRecord('does-not-exist', []));

        self::assertFalse($result->success);
    }

    public function testIsDisplayedOnlyForActiveStates(): void
    {
        $action = new CancelBulkJobAction(new InMemoryBulkJobStorage());

        self::assertTrue($action->isDisplayed(['record' => new DataRecord('id', ['status' => 'pending'])]));
        self::assertTrue($action->isDisplayed(['record' => new DataRecord('id', ['status' => 'running'])]));
        self::assertFalse($action->isDisplayed(['record' => new DataRecord('id', ['status' => 'completed'])]));
        self::assertFalse($action->isDisplayed(['record' => new DataRecord('id', ['status' => 'cancelled'])]));
    }

    public function testPermissionGate(): void
    {
        self::assertSame(CancelBulkJobAction::PERMISSION, (new CancelBulkJobAction(new InMemoryBulkJobStorage()))->getPermission());
    }

    private function makeJob(BulkJobStatus $status): BulkJob
    {
        return new BulkJob(
            id: 'job-cancel-test',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: ['r-1', 'r-2'],
            status: $status,
        );
    }
}
