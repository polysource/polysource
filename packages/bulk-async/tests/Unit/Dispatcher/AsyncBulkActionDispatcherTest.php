<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Dispatcher;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Dispatcher\AsyncBulkActionDispatcher;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Messenger\BulkJobMessage;
use Polysource\BulkAsync\Tests\InMemory\InMemoryBulkJobStorage;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class AsyncBulkActionDispatcherTest extends TestCase
{
    public function testPersistsPendingJobAndDispatchesMessage(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $bus = new SpyMessageBus();
        $dispatcher = new AsyncBulkActionDispatcher($storage, $bus);

        $job = $dispatcher->dispatch(
            new FakeOrdersResource(),
            new FakeRetryAction(),
            ['ord-1', 'ord-2', 'ord-3'],
            'alice',
        );

        self::assertSame(BulkJobStatus::Pending, $job->status);
        self::assertSame('orders', $job->resourceName);
        self::assertSame('retry-all', $job->actionName);
        self::assertSame('alice', $job->actorId);
        self::assertSame(['ord-1', 'ord-2', 'ord-3'], $job->recordIds);
        self::assertNotEmpty($job->id);

        self::assertSame($job, $storage->find($job->id));
        self::assertCount(1, $bus->messages);
        self::assertInstanceOf(BulkJobMessage::class, $bus->messages[0]);
        self::assertSame($job->id, $bus->messages[0]->jobId);
    }

    public function testRejectsEmptyActor(): void
    {
        $dispatcher = new AsyncBulkActionDispatcher(new InMemoryBulkJobStorage(), new SpyMessageBus());

        $this->expectException(InvalidArgumentException::class);
        $dispatcher->dispatch(new FakeOrdersResource(), new FakeRetryAction(), ['ord-1'], '');
    }

    public function testRejectsEmptyRecordList(): void
    {
        $dispatcher = new AsyncBulkActionDispatcher(new InMemoryBulkJobStorage(), new SpyMessageBus());

        $this->expectException(InvalidArgumentException::class);
        $dispatcher->dispatch(new FakeOrdersResource(), new FakeRetryAction(), [], 'alice');
    }
}

final class SpyMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = $message instanceof Envelope ? $message : new Envelope($message);
        $this->messages[] = $envelope->getMessage();

        return $envelope;
    }
}

final class FakeOrdersResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new NullDataSource());
    }

    public function getName(): string
    {
        return 'orders';
    }

    public function getLabel(): string
    {
        return 'Orders';
    }
}

final class FakeRetryAction implements BulkActionInterface
{
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
        return ActionResult::success();
    }
}

final class NullDataSource implements DataSourceInterface
{
    public function search(DataQuery $query): DataPage
    {
        return new DataPage([], 0);
    }

    public function find(string|int $identifier): ?DataRecord
    {
        return null;
    }

    public function count(DataQuery $query): ?int
    {
        unset($query);

        return null;
    }
}
