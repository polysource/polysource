<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Unit\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Messenger\Action\PurgeFailedMessagesAction;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

#[CoversClass(PurgeFailedMessagesAction::class)]
final class PurgeFailedMessagesActionTest extends TestCase
{
    #[Test]
    public function acksEveryEnvelopeWithoutDispatching(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1'),
            self::env('2'),
            self::env('3'),
        ]);
        $action = new PurgeFailedMessagesAction($receiver);

        $result = $action->executeBatch([]);

        self::assertTrue($result->success);
        self::assertSame([], iterator_to_array($receiver->all()));
        self::assertNotNull($result->message);
        self::assertStringContainsString('3 messages purged', $result->message);
    }

    #[Test]
    public function honoursMaxPurgeCap(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1'),
            self::env('2'),
            self::env('3'),
            self::env('4'),
        ]);
        $action = new PurgeFailedMessagesAction($receiver, maxPurge: 2);

        $action->executeBatch([]);

        self::assertCount(2, iterator_to_array($receiver->all()));
    }

    private static function env(string $id): Envelope
    {
        return (new Envelope(new PlainMessage('msg-' . $id, 1)))
            ->with(new TransportMessageIdStamp($id));
    }
}
