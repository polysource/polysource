<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch\DataSource;

use Polysource\Adapter\Meilisearch\Client\MeilisearchIndexInterface;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\SortDirection;
use RuntimeException;

/**
 * Read+write data source over a single Meilisearch index.
 *
 * **Search-first design** — Meilisearch is built around full-text
 * relevance scoring; the `DataQuery::$searchText` becomes the
 * primary query string and filters are translated into Meilisearch's
 * filter expressions (`field = value`, `field IN [a, b]`,
 * `field > 100`, …).
 *
 * Cf. ADR-002 — Meilisearch returns `estimatedTotalHits` for fast
 * approximate counts; we expose it as `total` (it's good enough for
 * UI pagination footers).
 *
 * Filter property whitelist comes from the host's index settings —
 * Meilisearch refuses filtering on attributes not declared as
 * `filterableAttributes`. We mirror that contract by accepting any
 * property and letting Meilisearch reject; a typo surfaces as a
 * server-side error rather than a silent skip.
 *
 * Write side: documents are JSON maps keyed by the configured
 * `primaryKey` (default `id`). `update` and `create` both call
 * `addDocuments()` — Meilisearch's upsert semantics make them
 * functionally identical.
 */
final class MeilisearchDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private readonly MeilisearchIndexInterface $index,
        private readonly string $primaryKey = 'id',
        private readonly int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        $pagination = $query->pagination;
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;
        $offset = null === $pagination ? 0 : $pagination->offset;

        $options = [
            'limit' => $limit,
            'offset' => $offset,
        ];

        $filters = self::buildFilterExpression($query);
        if ('' !== $filters) {
            $options['filter'] = $filters;
        }

        $sort = self::buildSortExpression($query);
        if ([] !== $sort) {
            $options['sort'] = $sort;
        }

        $response = $this->index->search($query->searchText ?? '', $options);

        $records = [];
        foreach ($response['hits'] as $hit) {
            $records[] = $this->toDataRecord($hit);
        }

        return new DataPage(
            items: $records,
            total: $response['estimatedTotalHits'] ?? $response['totalHits'],
        );
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $document = $this->index->getDocument((string) $identifier);
        if (null === $document) {
            return null;
        }

        return $this->toDataRecord($document);
    }

    public function count(DataQuery $query): ?int
    {
        return $this->search($query)->total;
    }

    public function create(DataPayload $payload): DataRecord
    {
        $document = $payload->properties;
        if (!isset($document[$this->primaryKey]) || !\is_scalar($document[$this->primaryKey])) {
            throw new RuntimeException(\sprintf('MeilisearchDataSource: payload must carry a scalar "%s" primary key.', $this->primaryKey));
        }

        $this->index->addDocuments([self::stringifyArrayKeys($document)]);

        return $this->toDataRecord($document);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $document = $payload->properties;
        $document[$this->primaryKey] = $identifier; // Force the id even if the payload omitted it

        $this->index->addDocuments([self::stringifyArrayKeys($document)]);

        return $this->toDataRecord($document);
    }

    public function delete(int|string $identifier): void
    {
        $this->index->deleteDocument((string) $identifier);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $document
     */
    private function toDataRecord(array $document): DataRecord
    {
        $rawId = $document[$this->primaryKey] ?? null;
        if (\is_int($rawId) || \is_string($rawId)) {
            $identifier = $rawId;
        } elseif (\is_scalar($rawId)) {
            $identifier = (string) $rawId;
        } else {
            $identifier = '';
        }

        /** @var array<string, mixed> $document */
        return new DataRecord($identifier, $document);
    }

    private static function buildFilterExpression(DataQuery $query): string
    {
        $clauses = [];
        foreach ($query->filters as $criterion) {
            $clause = self::criterionToClause($criterion);
            if ('' !== $clause) {
                $clauses[] = $clause;
            }
        }

        return implode(' AND ', $clauses);
    }

    private static function criterionToClause(FilterCriterion $criterion): string
    {
        $field = self::escapeField($criterion->property);
        $value = $criterion->value;

        return match ($criterion->operator) {
            'eq' => null === $value ? '' : \sprintf('%s = %s', $field, self::quote($value)),
            'neq' => null === $value ? '' : \sprintf('%s != %s', $field, self::quote($value)),
            'gt' => null === $value ? '' : \sprintf('%s > %s', $field, self::quote($value)),
            'gte' => null === $value ? '' : \sprintf('%s >= %s', $field, self::quote($value)),
            'lt' => null === $value ? '' : \sprintf('%s < %s', $field, self::quote($value)),
            'lte' => null === $value ? '' : \sprintf('%s <= %s', $field, self::quote($value)),
            'in' => \is_array($value) && [] !== $value
                ? \sprintf('%s IN [%s]', $field, implode(', ', array_map(self::quote(...), array_values($value))))
                : '',
            default => '',
        };
    }

    /**
     * @return list<string>
     */
    private static function buildSortExpression(DataQuery $query): array
    {
        $sort = [];
        foreach ($query->sort as $property => $direction) {
            $sort[] = self::escapeField($property) . ':' . (SortDirection::DESC === $direction ? 'desc' : 'asc');
        }

        return $sort;
    }

    private static function escapeField(string $field): string
    {
        // Meilisearch field names allow alnum + dot + underscore.
        // Strip everything else to avoid filter expression injection
        // through filter property names.
        return preg_replace('/[^a-zA-Z0-9_.]/', '', $field) ?? '';
    }

    private static function quote(mixed $value): string
    {
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        $string = '';
        if (\is_string($value)) {
            $string = $value;
        } elseif (\is_object($value) && method_exists($value, '__toString')) {
            $string = (string) $value;
        }

        // Escape single quotes per Meilisearch filter spec.
        return "'" . str_replace("'", "\\'", $string) . "'";
    }

    /**
     * Meilisearch documents must be string-keyed maps.
     *
     * @param array<int|string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private static function stringifyArrayKeys(array $document): array
    {
        $stringified = [];
        foreach ($document as $key => $value) {
            $stringified[(string) $key] = $value;
        }

        return $stringified;
    }
}
