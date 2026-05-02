<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Fixture;

use LogicException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * Minimal in-memory `ListableReceiverInterface` for adapter tests.
 *
 * Symfony's built-in InMemoryTransport does NOT implement
 * ListableReceiverInterface (only Doctrine, Redis and AMQP transports do
 * in core). We roll our own to keep the test suite hermetic.
 */
final class InMemoryListableReceiver implements ListableReceiverInterface
{
    /**
     * @var array<string, Envelope>
     */
    private array $envelopes = [];

    /**
     * @param iterable<Envelope> $seed each envelope MUST carry a TransportMessageIdStamp
     */
    public function __construct(iterable $seed = [])
    {
        foreach ($seed as $envelope) {
            $this->store($envelope);
        }
    }

    public function store(Envelope $envelope): void
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if (!$stamp instanceof TransportMessageIdStamp) {
            throw new LogicException('Test envelopes must carry a TransportMessageIdStamp.');
        }
        $this->envelopes[self::stringifyId($stamp->getId())] = $envelope;
    }

    public function all(?int $limit = null): iterable
    {
        $i = 0;
        foreach ($this->envelopes as $envelope) {
            if (null !== $limit && $i >= $limit) {
                return;
            }
            ++$i;
            yield $envelope;
        }
    }

    public function find(mixed $id): ?Envelope
    {
        return $this->envelopes[self::stringifyId($id)] ?? null;
    }

    private static function stringifyId(mixed $id): string
    {
        if (\is_string($id)) {
            return $id;
        }
        if (\is_scalar($id)) {
            return (string) $id;
        }
        if (\is_object($id) && method_exists($id, '__toString')) {
            return (string) $id;
        }

        throw new LogicException(\sprintf('Unsupported test id type %s — expected scalar or stringable.', get_debug_type($id)));
    }

    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }
}
