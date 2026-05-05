<?php

declare(strict_types=1);

namespace Polysource\Audit\DataSource;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
use Throwable;

/**
 * Read-only Polysource data source over the
 * `polysource_audit_log` table.
 *
 * Translates each `FilterCriterion` from the {@see DataQuery} into
 * a Doctrine `QueryBuilder` clause:
 *
 * | property      | operator | DQL fragment                         |
 * |---------------|----------|--------------------------------------|
 * | occurredAt    | between  | r.occurredAt BETWEEN :a AND :b       |
 * | occurredAt    | gte/lte  | r.occurredAt >= :a / <= :a           |
 * | actorId       | eq       | r.actorId = :a                       |
 * | resourceName  | in       | r.resourceName IN (:a)               |
 * | actionName    | in       | r.actionName IN (:a)                 |
 * | outcome       | in       | r.outcome IN (:a)                    |
 *
 * Unrecognised property/operator pairs are silently skipped — the
 * UI's filter form is the source of truth, the data source just
 * applies what it understands.
 *
 * Cf. ADR-020 §7. The 3 indexes on the table cover all the filter
 * combos this method generates.
 */
final class AuditLogDataSource implements DataSourceInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function search(DataQuery $query): DataPage
    {
        $qb = $this->buildQueryBuilder($query);

        // Newest first by default — operators reading the audit log
        // overwhelmingly want "what happened today / just now". The
        // index on occurred_at keeps this O(log n).
        $qb->orderBy('r.occurredAt', 'DESC');

        $pagination = $query->pagination;
        if (null !== $pagination) {
            $qb->setFirstResult($pagination->offset);
            $qb->setMaxResults($pagination->limit);
        }

        /** @var list<AuditEntryRecord> $records */
        $records = $qb->getQuery()->getResult();

        $items = [];
        foreach ($records as $record) {
            $items[] = $this->toDataRecord($record);
        }

        return new DataPage($items, $this->countWith($query));
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $record = $this->em->find(AuditEntryRecord::class, (string) $identifier);
        if (!$record instanceof AuditEntryRecord) {
            return null;
        }

        return $this->toDataRecord($record);
    }

    /**
     * Doctrine always knows the exact count cheaply (indexed COUNT
     * query). The interface allows null for adapters that can't —
     * we simply never return it. Return type stays nullable for LSP.
     *
     * @phpstan-ignore-next-line return.unusedType — interface contract is `?int`; we always know
     */
    public function count(DataQuery $query): ?int
    {
        return $this->countWith($query);
    }

    private function countWith(DataQuery $query): int
    {
        $qb = $this->buildQueryBuilder($query);
        $qb->select('COUNT(r.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function buildQueryBuilder(DataQuery $query): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(AuditEntryRecord::class, 'r');

        $bindIndex = 0;
        foreach ($query->filters as $criterion) {
            $this->applyCriterion($qb, $criterion, $bindIndex);
            ++$bindIndex;
        }

        return $qb;
    }

    private function applyCriterion(QueryBuilder $qb, FilterCriterion $criterion, int $bindIndex): void
    {
        $field = $this->mapProperty($criterion->property);
        if ('' === $field) {
            return;
        }
        $alias = 'r.' . $field;
        $param = ':p' . $bindIndex;

        match ($criterion->operator) {
            'between' => $this->applyBetween($qb, $alias, $criterion->value, $bindIndex),
            'eq' => $this->applyScalar($qb, "{$alias} = {$param}", $param, $criterion->value),
            'gte' => $this->applyScalar($qb, "{$alias} >= {$param}", $param, $criterion->value),
            'lte' => $this->applyScalar($qb, "{$alias} <= {$param}", $param, $criterion->value),
            'in' => $this->applyIn($qb, "{$alias} IN ({$param})", $param, $criterion->value),
            default => null, // unsupported — quietly skip
        };
    }

    /**
     * Whitelist of property names the data source understands. Maps
     * the filter form's property name to the entity field name.
     * Returns '' for unknown properties — caller skips the criterion.
     */
    private function mapProperty(string $property): string
    {
        return match ($property) {
            'occurredAt' => 'occurredAt',
            'actorId' => 'actorId',
            'resourceName' => 'resourceName',
            'actionName' => 'actionName',
            'outcome' => 'outcome',
            default => '',
        };
    }

    private function applyBetween(QueryBuilder $qb, string $alias, mixed $value, int $bindIndex): void
    {
        if (!\is_array($value) || 2 !== \count($value)) {
            return;
        }
        [$start, $end] = array_values($value);
        $a = ':p' . $bindIndex . 'a';
        $b = ':p' . $bindIndex . 'b';
        $qb->andWhere("{$alias} BETWEEN {$a} AND {$b}");
        $qb->setParameter(ltrim($a, ':'), $this->normaliseDateBound($start));
        $qb->setParameter(ltrim($b, ':'), $this->normaliseDateBound($end));
    }

    private function applyScalar(QueryBuilder $qb, string $clause, string $param, mixed $value): void
    {
        if (null === $value) {
            return;
        }
        $qb->andWhere($clause);
        $qb->setParameter(ltrim($param, ':'), $this->normaliseDateBound($value));
    }

    private function applyIn(QueryBuilder $qb, string $clause, string $param, mixed $value): void
    {
        if (!\is_array($value) || [] === $value) {
            return;
        }
        $qb->andWhere($clause);
        $qb->setParameter(ltrim($param, ':'), array_values($value));
    }

    /**
     * Coerce ISO-8601 date strings into DateTimeImmutable for the
     * occurredAt column. Other values pass through unchanged.
     */
    private function normaliseDateBound(mixed $value): mixed
    {
        if (\is_string($value) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private function toDataRecord(AuditEntryRecord $record): DataRecord
    {
        $recordIds = json_decode($record->recordIdsJson, true);
        $context = json_decode($record->contextJson, true);

        return new DataRecord($record->id, [
            'id' => $record->id,
            'occurredAt' => $record->occurredAt->format(\DATE_ATOM),
            'actorId' => $record->actorId,
            'actorLabel' => $record->actorLabel,
            'resourceName' => $record->resourceName,
            'actionName' => $record->actionName,
            'outcome' => $record->outcome,
            'message' => $record->message,
            'durationMs' => $record->durationMs,
            'recordIds' => \is_array($recordIds) ? $recordIds : [],
            'context' => \is_array($context) ? $context : [],
        ]);
    }
}
