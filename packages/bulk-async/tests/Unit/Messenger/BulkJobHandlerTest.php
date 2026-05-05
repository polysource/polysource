<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Messenger;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Audit\BulkActionView;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Messenger\BulkJobHandler;
use Polysource\BulkAsync\Messenger\BulkJobMessage;
use Polysource\BulkAsync\Tests\InMemory\InMemoryBulkJobStorage;
use Polysource\Bundle\Event\ActionExecutedEvent;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;
use Polysource\Core\Resource\ResourceInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class BulkJobHandlerTest extends TestCase
{
    public function testCompletesHappyPath(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $action = new RecordingAction();
        $resource = new RecordingResource(new MapDataSource([
            'r-1' => new DataRecord('r-1', ['n' => 1]),
            'r-2' => new DataRecord('r-2', ['n' => 2]),
            'r-3' => new DataRecord('r-3', ['n' => 3]),
        ]), [$action]);

        $job = $this->makeJob('job-happy', ['r-1', 'r-2', 'r-3']);
        $storage->save($job);

        $handler = new BulkJobHandler($storage, new ResourceRegistry([$resource]));
        $handler(new BulkJobMessage('job-happy'));

        $final = $storage->find('job-happy');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Completed, $final->status);
        self::assertSame(3, $final->processedCount);
        self::assertSame(0, $final->failedCount);
        self::assertNotNull($final->startedAt);
        self::assertNotNull($final->completedAt);
        self::assertSame(['r-1', 'r-2', 'r-3'], $action->seenIds);
    }

    public function testCountsFailuresAndMarksFailed(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $action = new RecordingAction(failOnIds: ['r-2']);
        $resource = new RecordingResource(new MapDataSource([
            'r-1' => new DataRecord('r-1', []),
            'r-2' => new DataRecord('r-2', []),
        ]), [$action]);

        $storage->save($this->makeJob('job-mixed', ['r-1', 'r-2']));

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('job-mixed'));

        $final = $storage->find('job-mixed');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Failed, $final->status);
        self::assertSame(2, $final->processedCount);
        self::assertSame(1, $final->failedCount);
    }

    public function testThrowingActionStillProgressesAndCounts(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $action = new RecordingAction(throwOnIds: ['r-1']);
        $resource = new RecordingResource(new MapDataSource([
            'r-1' => new DataRecord('r-1', []),
            'r-2' => new DataRecord('r-2', []),
        ]), [$action]);

        $storage->save($this->makeJob('job-throw', ['r-1', 'r-2']));

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('job-throw'));

        $final = $storage->find('job-throw');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Failed, $final->status);
        self::assertSame(1, $final->failedCount);
    }

    public function testHonoursMidLoopCancellation(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $action = new RecordingAction();
        $resource = new RecordingResource(new MapDataSource([
            'r-1' => new DataRecord('r-1', []),
            'r-2' => new DataRecord('r-2', []),
            'r-3' => new DataRecord('r-3', []),
        ]), [$action]);

        $storage->save($this->makeJob('job-cancel', ['r-1', 'r-2', 'r-3']));

        // Flip the live row to Cancelled when the action sees r-1 —
        // i.e. before the next iteration's re-fetch.
        $action->onProcess = static function (string $id) use ($storage): void {
            if ('r-1' === $id) {
                $current = $storage->find('job-cancel');
                self::assertInstanceOf(BulkJob::class, $current);
                $storage->poke($current->withStatus(BulkJobStatus::Cancelled));
            }
        };

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('job-cancel'));

        $final = $storage->find('job-cancel');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Cancelled, $final->status);
        self::assertSame(['r-1'], $action->seenIds, 'Worker should stop before r-2.');
        self::assertSame(1, $final->processedCount);
    }

    public function testSkipsWhenJobAlreadyTerminal(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $resource = new RecordingResource(new MapDataSource([]), [new RecordingAction()]);
        $storage->save($this->makeJob('job-done', ['r-1'])->withStatus(BulkJobStatus::Completed));

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('job-done'));

        $final = $storage->find('job-done');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Completed, $final->status, 'Re-delivery must be a no-op.');
        self::assertSame(0, $final->processedCount);
    }

    public function testSkipsWhenJobMissing(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $resource = new RecordingResource(new MapDataSource([]), [new RecordingAction()]);

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('does-not-exist'));

        self::assertSame([], $storage->saves);
    }

    public function testFailsWhenResourceUnknown(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $storage->save($this->makeJob('job-no-res', ['r-1']));

        (new BulkJobHandler($storage, new ResourceRegistry([])))(new BulkJobMessage('job-no-res'));

        $final = $storage->find('job-no-res');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Failed, $final->status);
        self::assertNotNull($final->errorMessage);
        self::assertStringContainsString('not registered', $final->errorMessage);
    }

    public function testFailsWhenActionMissing(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $resource = new RecordingResource(new MapDataSource([]), []);
        $storage->save($this->makeJob('job-no-action', ['r-1']));

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('job-no-action'));

        $final = $storage->find('job-no-action');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(BulkJobStatus::Failed, $final->status);
    }

    public function testDispatchesAuditEventOnCompletion(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $action = new RecordingAction();
        $resource = new RecordingResource(new MapDataSource([
            'r-1' => new DataRecord('r-1', []),
        ]), [$action]);

        $storage->save($this->makeJob('job-audit', ['r-1']));

        $captured = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ActionExecutedEvent::class, static function (ActionExecutedEvent $event) use (&$captured): void {
            $captured[] = $event;
        });

        (new BulkJobHandler($storage, new ResourceRegistry([$resource]), null, $dispatcher))(new BulkJobMessage('job-audit'));

        self::assertCount(1, $captured);
        $event = $captured[0];
        self::assertInstanceOf(BulkActionView::class, $event->action);
        self::assertSame('bulk:retry-all', $event->action->getName());
        self::assertSame('orders', $event->resource->getName());
        self::assertSame(['r-1'], $event->recordIds);
        self::assertTrue($event->result->success);
        self::assertSame(1, $event->result->context['processedCount']);
        self::assertSame(0, $event->result->context['failedCount']);
        self::assertSame('job-audit', $event->result->context['jobId']);
    }

    public function testThrottlesProgressFlushes(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $action = new RecordingAction();
        $records = [];
        $ids = [];
        for ($i = 1; $i <= 7; ++$i) {
            $id = 'r-' . $i;
            $ids[] = $id;
            $records[$id] = new DataRecord($id, []);
        }
        $resource = new RecordingResource(new MapDataSource($records), [$action]);
        $storage->save($this->makeJob('job-throttle', $ids));

        (new BulkJobHandler($storage, new ResourceRegistry([$resource])))(new BulkJobMessage('job-throttle'));

        // Initial Pending save (1) + Running flip (2) + flush at 5 records (3)
        // + final flush (4) — anything more would mean per-record flushes.
        // Mid-iteration time-based flushes can add 1-2 more saves on slow
        // CI; allow up to 6 to keep the test stable while still proving
        // throttling kicks in below `records + 1`.
        self::assertGreaterThanOrEqual(4, \count($storage->saves));
        self::assertLessThanOrEqual(6, \count($storage->saves));
        $final = $storage->find('job-throttle');
        self::assertInstanceOf(BulkJob::class, $final);
        self::assertSame(7, $final->processedCount);
    }

    /**
     * @param list<string> $recordIds
     */
    private function makeJob(string $id, array $recordIds): BulkJob
    {
        return new BulkJob(
            id: $id,
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: $recordIds,
            status: BulkJobStatus::Pending,
        );
    }
}

final class RecordingResource extends AbstractResource implements ResourceInterface
{
    /**
     * @param list<ActionInterface> $actions
     */
    public function __construct(
        DataSourceInterface $dataSource,
        private readonly array $actions,
    ) {
        parent::__construct($dataSource);
    }

    public function getName(): string
    {
        return 'orders';
    }

    public function getLabel(): string
    {
        return 'Orders';
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }
}

final class MapDataSource implements DataSourceInterface
{
    /**
     * @param array<string, DataRecord> $records
     */
    public function __construct(private readonly array $records)
    {
    }

    public function search(DataQuery $query): DataPage
    {
        return new DataPage(array_values($this->records), \count($this->records));
    }

    public function find(string|int $identifier): ?DataRecord
    {
        return $this->records[(string) $identifier] ?? null;
    }

    public function count(DataQuery $query): ?int
    {
        unset($query);

        return \count($this->records) > 0 ? \count($this->records) : null;
    }
}

final class RecordingAction implements BulkActionInterface
{
    /** @var list<string> */
    public array $seenIds = [];

    /** @var (callable(string): void)|null */
    public $onProcess;

    /**
     * @param list<string> $failOnIds
     * @param list<string> $throwOnIds
     */
    public function __construct(
        public readonly array $failOnIds = [],
        public readonly array $throwOnIds = [],
    ) {
    }

    public function getName(): string
    {
        return 'retry-all';
    }

    public function getLabel(): string
    {
        return 'Retry all';
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getPermission(): ?string
    {
        return null;
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function executeBatch(iterable $records): ActionResult
    {
        foreach ($records as $record) {
            $id = (string) $record->identifier;
            $this->seenIds[] = $id;

            if (null !== $this->onProcess) {
                ($this->onProcess)($id);
            }

            if (\in_array($id, $this->throwOnIds, true)) {
                throw new RuntimeException('synthetic failure for ' . $id);
            }
            if (\in_array($id, $this->failOnIds, true)) {
                return ActionResult::failure('failed ' . $id);
            }
        }

        return ActionResult::success();
    }
}
