<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Unit\DataSource;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\Pagination;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

#[CoversClass(MessengerFailedDataSource::class)]
final class MessengerFailedDataSourceTest extends TestCase
{
    #[Test]
    public function searchReturnsAllEnvelopesAsRecords(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1', new PlainMessage('a', 1)),
            self::env('2', new PlainMessage('b', 2)),
        ]);
        $dataSource = new MessengerFailedDataSource($receiver, new EnvelopeMapper());

        $page = $dataSource->search(new DataQuery('failed-messages'));

        self::assertNull($page->total);
        $items = $page->asArray();
        self::assertCount(2, $items);
        self::assertSame('1', $items[0]->identifier);
        self::assertSame('2', $items[1]->identifier);
        self::assertSame(PlainMessage::class, $items[0]->get('message_class'));
    }

    #[Test]
    public function searchHonoursPaginationLimit(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1', new PlainMessage('a', 1)),
            self::env('2', new PlainMessage('b', 2)),
            self::env('3', new PlainMessage('c', 3)),
        ]);
        $dataSource = new MessengerFailedDataSource($receiver, new EnvelopeMapper());

        $query = (new DataQuery('failed-messages'))->withPagination(new Pagination(offset: 0, limit: 2));
        $page = $dataSource->search($query);

        self::assertCount(2, $page->asArray());
    }

    #[Test]
    public function searchSkipsRecordsAccordingToPaginationOffset(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1', new PlainMessage('a', 1)),
            self::env('2', new PlainMessage('b', 2)),
            self::env('3', new PlainMessage('c', 3)),
            self::env('4', new PlainMessage('d', 4)),
            self::env('5', new PlainMessage('e', 5)),
        ]);
        $dataSource = new MessengerFailedDataSource($receiver, new EnvelopeMapper());

        // page 2 with pageSize 2 → offset 2, limit 2 → ids 3 and 4
        $query = (new DataQuery('failed-messages'))->withPagination(new Pagination(offset: 2, limit: 2));
        $page = $dataSource->search($query);

        $items = $page->asArray();
        self::assertCount(2, $items);
        self::assertSame('3', $items[0]->identifier);
        self::assertSame('4', $items[1]->identifier);
    }

    #[Test]
    public function findReturnsRecordForKnownId(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('abc', new PlainMessage('a', 1)),
        ]);
        $dataSource = new MessengerFailedDataSource($receiver, new EnvelopeMapper());

        $record = $dataSource->find('abc');

        self::assertNotNull($record);
        self::assertSame('abc', $record->identifier);
    }

    #[Test]
    public function findReturnsNullForUnknownId(): void
    {
        $receiver = new InMemoryListableReceiver();
        $dataSource = new MessengerFailedDataSource($receiver, new EnvelopeMapper());

        self::assertNull($dataSource->find('missing'));
    }

    #[Test]
    public function countAlwaysReturnsNullForCursorSemantics(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1', new PlainMessage('a', 1)),
        ]);
        $dataSource = new MessengerFailedDataSource($receiver, new EnvelopeMapper());

        self::assertNull($dataSource->count(new DataQuery('failed-messages')));
    }

    #[Test]
    public function rejectsNonListableReceiver(): void
    {
        $nonListable = new InMemoryTransport(new PhpSerializer());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('ListableReceiverInterface');
        new MessengerFailedDataSource($nonListable, new EnvelopeMapper());
    }

    private static function env(string $id, object $message): Envelope
    {
        return (new Envelope($message))->with(new TransportMessageIdStamp($id));
    }
}
