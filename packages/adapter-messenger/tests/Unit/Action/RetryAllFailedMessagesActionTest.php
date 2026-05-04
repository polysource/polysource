<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Unit\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Messenger\Action\RetryAllFailedMessagesAction;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use Polysource\Adapter\Messenger\Tests\Fixture\SpyMessageBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

#[CoversClass(RetryAllFailedMessagesAction::class)]
final class RetryAllFailedMessagesActionTest extends TestCase
{
    #[Test]
    public function dispatchesEveryEnvelopeAndAcksThemAll(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1'),
            self::env('2'),
            self::env('3'),
        ]);
        $bus = new SpyMessageBus();
        $action = new RetryAllFailedMessagesAction($bus, $receiver);

        $result = $action->executeBatch([]);

        self::assertTrue($result->success);
        self::assertCount(3, $bus->dispatched);
        self::assertSame([], [...$receiver->all()]);
        self::assertNotNull($result->message);
        self::assertStringContainsString('3 messages queued', $result->message);
    }

    #[Test]
    public function ignoresProvidedRecordsAndStillOperatesOnAll(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1'),
            self::env('2'),
        ]);
        $bus = new SpyMessageBus();
        $action = new RetryAllFailedMessagesAction($bus, $receiver);

        // Pass irrelevant records — must not influence the count.
        $result = $action->executeBatch([
            new \Polysource\Core\Query\DataRecord('zzz', []),
        ]);

        self::assertTrue($result->success);
        self::assertCount(2, $bus->dispatched);
    }

    #[Test]
    public function honoursMaxRetriesCap(): void
    {
        $receiver = new InMemoryListableReceiver([
            self::env('1'),
            self::env('2'),
            self::env('3'),
        ]);
        $bus = new SpyMessageBus();
        $action = new RetryAllFailedMessagesAction($bus, $receiver, maxRetries: 2);

        $action->executeBatch([]);

        self::assertCount(2, $bus->dispatched);
    }

    private static function env(string $id): Envelope
    {
        return (new Envelope(new PlainMessage('msg-' . $id, 1)))
            ->with(new TransportMessageIdStamp($id))
            ->with(new SentToFailureTransportStamp('async'));
    }
}
