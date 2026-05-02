<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Unit\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Messenger\Action\RetryFailedMessageAction;
use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use Polysource\Adapter\Messenger\Tests\Fixture\SpyMessageBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

#[CoversClass(RetryFailedMessageAction::class)]
final class RetryFailedMessageActionTest extends TestCase
{
    #[Test]
    public function dispatchesEnvelopeStrippedOfFailureStampsAndAcksOriginal(): void
    {
        $envelope = (new Envelope(new PlainMessage('payment', 1)))
            ->with(new TransportMessageIdStamp('abc'))
            ->with(new SentToFailureTransportStamp('async'))
            ->with(new ReceivedStamp('failed'))
        ;
        $receiver = new InMemoryListableReceiver([$envelope]);
        $bus = new SpyMessageBus();
        $action = new RetryFailedMessageAction($bus, $receiver);
        $record = (new EnvelopeMapper())->map($envelope);

        $result = $action->execute($record);

        self::assertTrue($result->success);
        self::assertCount(1, $bus->dispatched);
        $reborn = $bus->dispatched[0];
        self::assertNull($reborn->last(SentToFailureTransportStamp::class));
        self::assertNull($reborn->last(ReceivedStamp::class));
        self::assertNull($reborn->last(TransportMessageIdStamp::class));
        // Envelope was acked → no longer findable.
        self::assertNull($receiver->find('abc'));
    }

    #[Test]
    public function failsGracefullyOnRecordWithoutEnvelope(): void
    {
        $action = new RetryFailedMessageAction(new SpyMessageBus(), new InMemoryListableReceiver());
        $record = new \Polysource\Core\Query\DataRecord('orphan', []);

        $result = $action->execute($record);

        self::assertFalse($result->success);
        self::assertNotNull($result->message);
        self::assertStringContainsString('orphan', $result->message);
    }
}
