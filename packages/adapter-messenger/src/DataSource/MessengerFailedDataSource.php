<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DataSource;

use LogicException;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
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
        // ListableReceiverInterface::all() takes only a limit and
        // exposes no native filtering, so we materialise the whole
        // listing (up to the receiver's safety cap) and apply
        // filters + pagination in PHP. Acceptable cost: failed
        // transports are usually small (< 1k envelopes), and ops
        // need filters to triage incidents.
        $pagination = $query->pagination;
        $offset = null === $pagination ? 0 : $pagination->offset;
        $limit = null === $pagination ? null : $pagination->limit;

        $hasFilters = $query->filters !== [];

        $fetchCount = match (true) {
            // With filters, we can't truncate at offset+limit because
            // the after-filter ranks differ from the receiver's order.
            $hasFilters => null,
            null === $limit => null,
            default => $offset + $limit,
        };

        $matched = [];
        foreach ($this->receiver->all($fetchCount) as $envelope) {
            try {
                $record = $this->mapper->map($envelope);
            } catch (Throwable $e) {
                // One unmappable envelope must not crash the whole
                // index — skip it, log, keep going.
                $this->logger->warning('Polysource Messenger: skipping unmappable envelope.', [
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);

                continue;
            }
            if ($hasFilters && !self::matchesAllFilters($record, $query->filters)) {
                continue;
            }
            $matched[] = $record;
        }

        $page = $matched;
        if ($offset > 0) {
            $page = \array_slice($page, $offset);
        }
        if (null !== $limit) {
            $page = \array_slice($page, 0, $limit);
        }

        return new DataPage(items: $page, total: null);
    }

    /**
     * @param array<string, FilterCriterion> $filters
     */
    private static function matchesAllFilters(DataRecord $record, array $filters): bool
    {
        foreach ($filters as $criterion) {
            $value = $record->properties[$criterion->property] ?? null;
            if (!self::matchesCriterion($value, $criterion->operator, $criterion->value)) {
                return false;
            }
        }

        return true;
    }

    private static function matchesCriterion(mixed $value, FilterOperator $operator, mixed $expected): bool
    {
        // Operator dispatch shared with adapter-flysystem and
        // adapter-redis — extracted to core in v0.10.0 (audit task
        // #65). All 12 FilterOperator variants implemented by the
        // shared matcher, matching the previous Messenger-specific
        // behaviour 1:1.
        return \Polysource\Core\Query\InMemoryValueMatcher::matches($value, $operator, $expected);
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
