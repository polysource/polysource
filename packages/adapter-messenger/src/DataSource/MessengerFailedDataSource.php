<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DataSource;

use LogicException;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * Read-only Polysource data source backed by a Messenger failed transport.
 *
 * Cf. ADR-002 — `count()` returns `null` because listable receivers do not
 * cheaply expose a total count; the UI uses cursor-style pagination.
 *
 * The receiver MUST implement {@see ListableReceiverInterface}. Doctrine,
 * Redis, AMQP and InMemory transports all do; SQS and Beanstalk do not.
 * Non-listable receivers fail fast at construction time with a
 * {@see LogicException}.
 */
final readonly class MessengerFailedDataSource implements DataSourceInterface
{
    private ListableReceiverInterface $receiver;

    public function __construct(
        ReceiverInterface $receiver,
        private EnvelopeMapper $mapper,
    ) {
        if (!$receiver instanceof ListableReceiverInterface) {
            throw new LogicException(\sprintf('Polysource Messenger adapter requires a ListableReceiverInterface; got %s. Configure a listable failed transport (Doctrine, Redis, AMQP, InMemory).', $receiver::class));
        }
        $this->receiver = $receiver;
    }

    public function search(DataQuery $query): DataPage
    {
        $limit = $query->pagination?->limit;

        $records = [];
        foreach ($this->receiver->all($limit) as $envelope) {
            $records[] = $this->mapper->map($envelope);
        }

        return new DataPage(items: $records, total: null);
    }

    public function find(string|int $identifier): ?DataRecord
    {
        $envelope = $this->receiver->find($identifier);
        if (null === $envelope) {
            return null;
        }

        return $this->mapper->map($envelope);
    }

    public function count(DataQuery $query): ?int
    {
        return null;
    }
}
