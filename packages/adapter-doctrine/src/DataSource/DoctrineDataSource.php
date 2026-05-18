<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\ClassMetadata;
use InvalidArgumentException;
use Polysource\Adapter\Doctrine\Hydration\EntityRecordHydrator;
use Polysource\Adapter\Doctrine\Query\CriterionApplier;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\SortDirection;
use RuntimeException;

/**
 * Generic Doctrine ORM data source — read+write over any mapped entity.
 *
 * Why a single generic class rather than one per entity:
 *  - The contract is uniform — `EntityManager::find()`, QueryBuilder
 *    on the FROM, persist/flush. Writing one class per entity is
 *    boilerplate explosion for zero added value.
 *  - Hosts compose with the Resource (cf. ADR-005 + the
 *    `DoctrineEntityResource` base) which carries the
 *    entity-specific identity (FQCN, identifier property, label).
 *
 * Filter mapping is **whitelist-based**. Hosts pass the list of
 * allowed properties at construction; criteria targeting other
 * properties are silently skipped — same convention as
 * AuditLogDataSource. This avoids accidental SQL surface exposure
 * (a typo in a filter form must never become a where clause on a
 * sensitive column).
 *
 * Pagination: offset/limit through QueryBuilder; `count()` returns
 * an exact value via a COUNT query — Doctrine has the indexes for
 * it.
 *
 * Cf. ADR-001 / ADR-002 / ADR-012 (cohabitation case).
 */
final class DoctrineDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_OPERATORS = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'between', 'like'];

    /** @var class-string */
    private readonly string $entityClass;

    /** @var ClassMetadata<object> */
    private readonly ClassMetadata $metadata;

    /** @var array<string, string> map of public property → entity field */
    private readonly array $allowedFilters;

    /** @var array<string, string> map of public property → entity field */
    private readonly array $allowedSorts;

    /**
     * @param class-string                                     $entityClass
     * @param array<string, string>|null $allowedFilters    map of public property → entity field; null = all entity fields, mapped 1-1
     * @param array<string, string>|null $allowedSorts      same shape as filters; null = same as filters
     * @param string|null                                      $searchProperty optional LIKE-search property for `DataQuery::$searchText`
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        string $entityClass,
        ?array $allowedFilters = null,
        ?array $allowedSorts = null,
        private readonly ?string $searchProperty = null,
    ) {
        if (!class_exists($entityClass)) {
            throw new InvalidArgumentException(\sprintf('DoctrineDataSource: entity class "%s" does not exist.', $entityClass));
        }
        $this->entityClass = $entityClass;
        $this->metadata = $em->getClassMetadata($entityClass);

        $defaultFields = self::defaultFieldMap($this->metadata);
        $this->allowedFilters = $allowedFilters ?? $defaultFields;
        $this->allowedSorts = $allowedSorts ?? $this->allowedFilters;

        // Validate searchProperty against the entity's mapped fields. Without this,
        // a misconfigured wiring (compromised .env, env-injection, copy-paste typo)
        // would let `searchProperty` flow into `sprintf('r.%s LIKE :search', ...)`
        // unchecked — same SQL-surface footgun the allowedFilters whitelist already
        // closes for explicit filters. Pre-v0.1.0 security audit caught this asymmetry.
        if (null !== $this->searchProperty && !\in_array($this->searchProperty, $this->metadata->getFieldNames(), true)) {
            throw new InvalidArgumentException(\sprintf('DoctrineDataSource: searchProperty "%s" is not a mapped field of %s. Known fields: %s.', $this->searchProperty, $entityClass, implode(', ', $this->metadata->getFieldNames())));
        }
    }

    public function search(DataQuery $query): DataPage
    {
        $qb = $this->buildQueryBuilder($query);
        $this->applySorts($qb, $query);

        $pagination = $query->pagination;
        if (null !== $pagination) {
            $qb->setFirstResult($pagination->offset);
            $qb->setMaxResults($pagination->limit);
        }

        /** @var list<object> $entities */
        $entities = $qb->getQuery()->getResult();

        $items = array_map(fn (object $entity): DataRecord => $this->toDataRecord($entity), $entities);

        return new DataPage($items, $this->countWith($query));
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $entity = $this->em->find($this->entityClass, $identifier);
        if (null === $entity) {
            return null;
        }

        return $this->toDataRecord($entity);
    }

    /**
     * Doctrine always returns an exact count via the indexed COUNT
     * query. PHP covariant returns let the impl narrow the
     * interface's `?int` to a non-nullable `int` — adapters that
     * can't count efficiently (Messenger, Redis SCAN) keep the
     * nullable return.
     */
    public function count(DataQuery $query): int
    {
        return $this->countWith($query);
    }

    public function create(DataPayload $payload): DataRecord
    {
        $entity = $this->instantiate();
        $this->applyPayload($entity, $payload);

        $this->em->persist($entity);
        $this->em->flush();

        return $this->toDataRecord($entity);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $entity = $this->em->find($this->entityClass, $identifier);
        if (null === $entity) {
            throw new RuntimeException(\sprintf('DoctrineDataSource: %s with id "%s" not found.', $this->entityClass, (string) $identifier));
        }

        $this->applyPayload($entity, $payload);
        $this->em->flush();

        return $this->toDataRecord($entity);
    }

    public function delete(int|string $identifier): void
    {
        $entity = $this->em->find($this->entityClass, $identifier);
        if (null === $entity) {
            return; // idempotent — same convention as RetryFailedMessageAction
        }

        $this->em->remove($entity);
        $this->em->flush();
    }

    private function countWith(DataQuery $query): int
    {
        $qb = $this->buildQueryBuilder($query);

        $idField = $this->metadata->getSingleIdentifierFieldName();
        $qb->select(\sprintf('COUNT(r.%s)', $idField));

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function buildQueryBuilder(DataQuery $query): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from($this->entityClass, 'r');

        // Criterion → WHERE delegation extracted to CriterionApplier
        // in v0.10.0 so the operator dispatch is unit-testable in
        // isolation from the data-source coordination.
        $bindIndex = 0;
        foreach ($query->filters as $criterion) {
            CriterionApplier::apply($qb, $criterion, $bindIndex, $this->allowedFilters);
            ++$bindIndex;
        }

        if (null !== $query->searchText && '' !== $query->searchText && null !== $this->searchProperty) {
            $qb->andWhere(\sprintf('r.%s LIKE :search', $this->searchProperty));
            $qb->setParameter('search', '%' . $query->searchText . '%');
        }

        return $qb;
    }

    private function applySorts(QueryBuilder $qb, DataQuery $query): void
    {
        if ([] === $query->sort) {
            $idField = $this->metadata->getSingleIdentifierFieldName();
            $qb->orderBy('r.' . $idField, 'ASC');

            return;
        }

        foreach ($query->sort as $property => $direction) {
            $field = $this->allowedSorts[$property] ?? null;
            if (null === $field) {
                continue;
            }
            $qb->addOrderBy('r.' . $field, SortDirection::DESC === $direction ? 'DESC' : 'ASC');
        }
    }

    private function applyPayload(object $entity, DataPayload $payload): void
    {
        EntityRecordHydrator::applyPayload($this->metadata, $entity, $payload, $this->allowedFilters);
    }

    private function instantiate(): object
    {
        return EntityRecordHydrator::instantiate($this->metadata);
    }

    private function toDataRecord(object $entity): DataRecord
    {
        return EntityRecordHydrator::toRecord($this->metadata, $entity);
    }

    /**
     * @return array<string, string>
     */
    /**
     * @param ClassMetadata<object> $metadata
     *
     * @return array<string, string>
     */
    private static function defaultFieldMap(ClassMetadata $metadata): array
    {
        $map = [];
        foreach ($metadata->getFieldNames() as $field) {
            $map[$field] = $field;
        }

        return $map;
    }
}
