<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Unit\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Messenger\Action\DismissFailedMessageAction;
use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

#[CoversClass(DismissFailedMessageAction::class)]
final class DismissFailedMessageActionTest extends TestCase
{
    #[Test]
    public function acksTheEnvelopeWithoutDispatching(): void
    {
        $envelope = (new Envelope(new PlainMessage('email', 1)))
            ->with(new TransportMessageIdStamp('abc'));
        $receiver = new InMemoryListableReceiver([$envelope]);
        $action = new DismissFailedMessageAction($receiver);
        $record = (new EnvelopeMapper())->map($envelope);

        $result = $action->execute($record);

        self::assertTrue($result->success);
        self::assertNull($receiver->find('abc'));
    }

    #[Test]
    public function failsGracefullyOnRecordWithoutEnvelope(): void
    {
        $action = new DismissFailedMessageAction(new InMemoryListableReceiver());
        $record = new \Polysource\Core\Query\DataRecord('orphan', []);

        $result = $action->execute($record);

        self::assertFalse($result->success);
    }
}
