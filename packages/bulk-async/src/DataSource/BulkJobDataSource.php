<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\DataSource;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Job\Doctrine\BulkJobRecord;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Throwable;

/**
 * Read-only Polysource data source over the
 * `polysource_bulk_jobs` table — drives the browsable
 * {@see \Polysource\BulkAsync\Resource\BulkJobResource}.
 *
 * Translates each {@see FilterCriterion} into a Doctrine clause:
 *
 * | property      | operator        | clause                           |
 * |---------------|-----------------|----------------------------------|
 * | actorId       | eq              | r.actorId = :a                   |
 * | status        | in              | r.status IN (:a)                 |
 * | createdAt     | between/gte/lte | r.createdAt BETWEEN/>=/<= :a     |
 * | resourceName  | in              | r.resourceName IN (:a)           |
 *
 * The 3 indexes on the table cover all combos this method generates
 * (cf. ADR-024 §4).
 */
final class BulkJobDataSource implements DataSourceInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function search(DataQuery $query): DataPage
    {
        $qb = $this->buildQueryBuilder($query);
        $qb->orderBy('r.createdAt', 'DESC');

        $pagination = $query->pagination;
        if (null !== $pagination) {
            $qb->setFirstResult($pagination->offset);
            $qb->setMaxResults($pagination->limit);
        }

        /** @var list<BulkJobRecord> $records */
        $records = $qb->getQuery()->getResult();

        $items = [];
        foreach ($records as $record) {
            $items[] = $this->toDataRecord($record);
        }

        return new DataPage($items, $this->countWith($query));
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $record = $this->em->find(BulkJobRecord::class, (string) $identifier);
        if (!$record instanceof BulkJobRecord) {
            return null;
        }

        return $this->toDataRecord($record);
    }

    /**
     * Doctrine always knows the count cheaply (indexed status / actor
     * / created_at queries). Return type stays nullable for LSP.
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
            ->from(BulkJobRecord::class, 'r');

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
            FilterOperator::Between => $this->applyBetween($qb, $alias, $criterion->value, $bindIndex),
            FilterOperator::Eq => $this->applyScalar($qb, "{$alias} = {$param}", $param, $criterion->value),
            FilterOperator::Gte => $this->applyScalar($qb, "{$alias} >= {$param}", $param, $criterion->value),
            FilterOperator::Lte => $this->applyScalar($qb, "{$alias} <= {$param}", $param, $criterion->value),
            FilterOperator::In => $this->applyIn($qb, "{$alias} IN ({$param})", $param, $criterion->value),
            default => null,
        };
    }

    private function mapProperty(string $property): string
    {
        return match ($property) {
            'actorId' => 'actorId',
            'status' => 'status',
            'createdAt' => 'createdAt',
            'resourceName' => 'resourceName',
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

    private function toDataRecord(BulkJobRecord $record): DataRecord
    {
        $recordIds = json_decode($record->recordIdsJson, true);

        $total = \is_array($recordIds) ? \count($recordIds) : 0;
        $progressFloat = $total > 0 ? $record->processedCount / $total : 1.0;

        // Index column display: human-readable percentage with up to
        // one decimal, trailing zeros trimmed so "100.0%" reads as
        // "100%" and "0.0%" reads as "0%". Polysource v0.1 ships only
        // the abstract FieldInterface — no built-in PercentageField —
        // so pre-formatting at the data-source layer is the right
        // contract until v0.2 introduces concrete field types
        // (cf. ADR-011). Hosts that want the raw 0..1 float for
        // their own rendering pipeline read `record.rawSource.progress()`,
        // which is the BulkJob VO method (lossless).
        $progressDisplay = $total > 0
            ? rtrim(rtrim(\sprintf('%.1f', $progressFloat * 100), '0'), '.') . '%'
            : '100%';

        return new DataRecord(
            $record->id,
            [
                'id' => $record->id,
                'createdAt' => $record->createdAt->format(\DATE_ATOM),
                'startedAt' => $record->startedAt?->format(\DATE_ATOM),
                'completedAt' => $record->completedAt?->format(\DATE_ATOM),
                'resourceName' => $record->resourceName,
                'actionName' => $record->actionName,
                'actorId' => $record->actorId,
                'status' => $record->status,
                'processedCount' => $record->processedCount,
                'failedCount' => $record->failedCount,
                'total' => $total,
                'progress' => $progressDisplay,
                'errorMessage' => $record->errorMessage,
            ],
            // Expose the BulkJob VO as rawSource so host detail
            // templates can call `polysource_bulk_progress(record.rawSource)`
            // directly without re-querying the storage. The conversion
            // mirrors {@see \Polysource\BulkAsync\Job\DoctrineBulkJobStorage::find()};
            // duplicating it here avoids forcing a second DB roundtrip
            // per record.
            self::recordToBulkJob($record, $recordIds),
        );
    }

    /**
     * @param mixed $decodedRecordIds the json_decoded `recordIdsJson` payload
     */
    private static function recordToBulkJob(BulkJobRecord $record, mixed $decodedRecordIds): ?BulkJob
    {
        if (!\is_array($decodedRecordIds)) {
            return null;
        }
        /** @var list<non-empty-string> $recordIds */
        $recordIds = array_values(array_filter(
            $decodedRecordIds,
            static fn ($v): bool => \is_string($v) && '' !== $v,
        ));
        $status = BulkJobStatus::tryFrom($record->status) ?? BulkJobStatus::Pending;

        try {
            return new BulkJob(
                id: $record->id,
                createdAt: $record->createdAt,
                resourceName: $record->resourceName,
                actionName: $record->actionName,
                actorId: $record->actorId,
                recordIds: $recordIds,
                status: $status,
                processedCount: $record->processedCount,
                failedCount: $record->failedCount,
                startedAt: $record->startedAt,
                completedAt: $record->completedAt,
                errorMessage: $record->errorMessage,
            );
        } catch (Throwable) {
            // Defensive: if the row got corrupted (negative counts,
            // counts > total, etc.) the BulkJob constructor throws.
            // Don't break the listing — the DataRecord properties
            // still carry the raw values for the index template,
            // and the host template just won't render the progress
            // card for that one row.
            return null;
        }
    }
}
