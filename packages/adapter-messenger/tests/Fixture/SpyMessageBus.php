<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Fixture;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * MessageBus that records every dispatched envelope. Records the
 * envelope as-is so tests can assert on stamps it carried (or didn't
 * carry, e.g. SentToFailureTransportStamp removal in retry actions).
 */
final class SpyMessageBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = $message instanceof Envelope
            ? $message
            : Envelope::wrap($message, $stamps);
        $this->dispatched[] = $envelope;

        return $envelope->with(new HandledStamp(null, self::class));
    }
}
