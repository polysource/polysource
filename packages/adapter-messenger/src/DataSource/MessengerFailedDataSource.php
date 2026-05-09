<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DataSource;

use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
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

    private static function matchesCriterion(mixed $value, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq' => self::looseEquals($value, $expected),
            'neq' => !self::looseEquals($value, $expected),
            'in' => \is_array($expected) && self::isInList($value, $expected),
            'nin' => \is_array($expected) && !self::isInList($value, $expected),
            'like' => \is_string($value) && \is_string($expected) && false !== stripos($value, $expected),
            'gte' => self::compareDateOrScalar($value, $expected) >= 0,
            'lte' => self::compareDateOrScalar($value, $expected) <= 0,
            'gt' => self::compareDateOrScalar($value, $expected) > 0,
            'lt' => self::compareDateOrScalar($value, $expected) < 0,
            'between' => self::matchesBetween($value, $expected),
            'null' => null === $value,
            'notnull' => null !== $value,
            default => true, // unsupported operator — don't constrain
        };
    }

    private static function matchesBetween(mixed $value, mixed $expected): bool
    {
        if (!\is_array($expected) || 2 !== \count($expected)) {
            return false;
        }
        [$min, $max] = array_values($expected);

        return self::compareDateOrScalar($value, $min) >= 0
            && self::compareDateOrScalar($value, $max) <= 0;
    }

    /**
     * @param array<int|string, mixed> $list
     */
    private static function isInList(mixed $value, array $list): bool
    {
        $needle = self::asScalar($value);
        foreach ($list as $candidate) {
            if (self::asScalar($candidate) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function looseEquals(mixed $value, mixed $expected): bool
    {
        return self::asScalar($value) === self::asScalar($expected);
    }

    /**
     * Compare two values that may be DateTimeInterface instances or
     * scalar dates (ISO-8601 strings). Returns -1, 0, 1 like
     * spaceship — defaults to 0 for incompatible types so a misuse
     * does not erase rows arbitrarily.
     */
    private static function compareDateOrScalar(mixed $a, mixed $b): int
    {
        $left = self::toComparable($a);
        $right = self::toComparable($b);
        if (null === $left || null === $right) {
            return 0;
        }

        return $left <=> $right;
    }

    private static function toComparable(mixed $value): float|int|string|null
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (\is_string($value)) {
            try {
                return (new DateTimeImmutable($value))->getTimestamp();
            } catch (Throwable) {
                return $value;
            }
        }
        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        return null;
    }

    private static function asScalar(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
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
