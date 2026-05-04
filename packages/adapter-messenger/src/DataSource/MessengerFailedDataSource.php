<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DataSource;

use LogicException;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Throwable;

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
final class MessengerFailedDataSource implements DataSourceInterface
{
    private ListableReceiverInterface $receiver;
    private LoggerInterface $logger;

    public function __construct(
        ReceiverInterface $receiver,
        private readonly EnvelopeMapper $mapper,
        ?LoggerInterface $logger = null,
    ) {
        if (!$receiver instanceof ListableReceiverInterface) {
            throw new LogicException(\sprintf('Polysource Messenger adapter requires a ListableReceiverInterface; got %s. Configure a listable failed transport (Doctrine, Redis, AMQP, InMemory).', $receiver::class));
        }
        $this->receiver = $receiver;
        $this->logger = $logger ?? new NullLogger();
    }

    public function search(DataQuery $query): DataPage
    {
        // ListableReceiverInterface::all() takes only a limit. We
        // emulate offset by fetching `offset + limit` envelopes and
        // skipping the first `$offset`. O(offset+limit), not great for
        // deep pagination but Symfony's Doctrine / Redis / AMQP
        // transports don't expose a cheaper primitive.
        $pagination = $query->pagination;
        $offset = null === $pagination ? 0 : $pagination->offset;
        $limit = null === $pagination ? null : $pagination->limit;

        $fetchCount = null === $limit ? null : $offset + $limit;

        $records = [];
        $skipped = 0;
        foreach ($this->receiver->all($fetchCount) as $envelope) {
            if ($skipped < $offset) {
                ++$skipped;

                continue;
            }
            try {
                $records[] = $this->mapper->map($envelope);
            } catch (Throwable $e) {
                // One unmappable envelope must not crash the whole
                // index — skip it, log, keep going.
                $this->logger->warning('Polysource Messenger: skipping unmappable envelope.', [
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);
            }
            if (null !== $limit && \count($records) >= $limit) {
                break;
            }
        }

        return new DataPage(items: $records, total: null);
    }

    public function find(string|int $identifier): ?DataRecord
    {
        $envelope = $this->receiver->find($identifier);
        if (null === $envelope) {
            return null;
        }

        try {
            return $this->mapper->map($envelope);
        } catch (Throwable $e) {
            $this->logger->warning('Polysource Messenger: failed to map envelope on find().', [
                'identifier' => $identifier,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function count(DataQuery $query): ?int
    {
        return null;
    }
}
