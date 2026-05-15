<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\DataSource;

use Polysource\Adapter\Redis\Client\RedisHashClientInterface;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterOperator;
use RuntimeException;

/**
 * Read+write data source over a namespaced collection of Redis
 * hashes — typical use: feature flags, lightweight key-value
 * config, session metadata.
 *
 * Each record lives at the key `{prefix}{id}` and the hash fields
 * are the record's properties. The identifier carried by
 * {@see DataRecord} is the suffix of the key (without the prefix).
 *
 * Pagination is **offset/limit** since v0.1.0: `search()`
 * materialises every matching key via repeated SCAN until the
 * cursor exhausts (capped at 50 iterations, ~5k keys), sorts by
 * id for stable ordering across requests, then slices in memory.
 * `total` is therefore exposed (the count of materialised keys),
 * which lets the Twig paginator render the canonical
 * "X records — page Y/Z" summary + Prev/Next links instead of the
 * cursor-only fallback.
 *
 * Why offset over cursor: Redis SCAN's cursor is session-bound —
 * Prev/Next navigation across separate requests can't share a
 * cursor reliably. Materialising once per page request and slicing
 * gives consistent UX for the small-to-medium namespaces this
 * adapter targets. For collections of more than a few thousand
 * records, hosts run their own RediSearch / RedisJSON index and
 * ship a custom data source.
 *
 * `count()` still returns `null` (cheap path); `search()` exposes
 * the materialised total in the DataPage so the controller does
 * not need to call both.
 *
 * Filter mapping in v0.1 is **client-side** — Redis hashes don't
 * support server-side filtering; we'd need RediSearch for that.
 */
final class RedisHashDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private readonly RedisHashClientInterface $client,
        private readonly string $keyPrefix,
        private readonly int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        $pagination = $query->pagination;
        $offset = null === $pagination ? 0 : $pagination->offset;
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;

        // Materialise every matching key via SCAN until cursor exhausts.
        // Hard-capped at 50 iterations × COUNT 100 = 5000 keys against
        // pathological scans; hosts with bigger namespaces ship a
        // custom data source over RediSearch / RedisJSON.
        $records = [];
        $cursor = '0';
        $iterations = 0;
        do {
            [$cursor, $keys] = $this->client->scan($cursor, $this->keyPrefix . '*', 100);
            foreach ($keys as $key) {
                $hash = $this->client->hgetall($key);
                $id = substr($key, \strlen($this->keyPrefix));
                if ('' === $id || [] === $hash) {
                    continue;
                }
                $record = new DataRecord($id, $hash);
                if (!self::matchesFilters($record, $query)) {
                    continue;
                }
                // Dedupe by id — Redis SCAN can legitimately return
                // the same key twice across iterations.
                $records[$id] = $record;
            }
            ++$iterations;
        } while ('0' !== $cursor && $iterations < 50);

        // Stable order by id so page N lands on the same records
        // regardless of when the user navigates there.
        ksort($records);
        $allRecords = array_values($records);

        $total = \count($allRecords);
        $page = \array_slice($allRecords, $offset, $limit);

        return new DataPage(
            items: $page,
            total: $total,
        );
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $id = (string) $identifier;
        $key = $this->keyPrefix . $id;

        $hash = $this->client->hgetall($key);
        if ([] === $hash) {
            return null;
        }

        return new DataRecord($id, $hash);
    }

    /**
     * Cursor-based sources don't expose a cheap total count; we
     * return `null` per ADR-002 §pagination cursor.
     */
    public function count(DataQuery $query): ?int
    {
        unset($query);

        return null;
    }

    public function create(DataPayload $payload): DataRecord
    {
        $id = self::extractId($payload);
        $key = $this->keyPrefix . $id;

        if ($this->client->exists($key)) {
            throw new RuntimeException(\sprintf('RedisHashDataSource: record "%s" already exists.', $id));
        }

        $fields = self::stringifyFields($payload);
        $this->client->hmset($key, $fields);

        return new DataRecord($id, $fields);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $id = (string) $identifier;
        $key = $this->keyPrefix . $id;

        if (!$this->client->exists($key)) {
            throw new RuntimeException(\sprintf('RedisHashDataSource: record "%s" does not exist.', $id));
        }

        $fields = self::stringifyFields($payload);
        $this->client->hmset($key, $fields);

        // Re-fetch to expose any pre-existing fields the payload didn't touch.
        $hash = $this->client->hgetall($key);

        return new DataRecord($id, $hash);
    }

    public function delete(int|string $identifier): void
    {
        $this->client->del($this->keyPrefix . (string) $identifier);
    }

    private static function matchesFilters(DataRecord $record, DataQuery $query): bool
    {
        foreach ($query->filters as $criterion) {
            $value = $record->get($criterion->property);
            if (!self::matchesCriterion($value, $criterion->operator, $criterion->value)) {
                return false;
            }
        }

        if (null !== $query->searchText && '' !== $query->searchText) {
            $needle = strtolower($query->searchText);
            $haystack = strtolower(implode(' ', array_map(
                static fn ($v): string => \is_scalar($v) ? (string) $v : '',
                $record->properties,
            )));
            if (!str_contains($haystack, $needle)) {
                return false;
            }
        }

        return true;
    }

    private static function matchesCriterion(mixed $value, FilterOperator $operator, mixed $expected): bool
    {
        return match ($operator) {
            FilterOperator::Eq => self::looseEquals($value, $expected),
            FilterOperator::Neq => !self::looseEquals($value, $expected),
            FilterOperator::In => \is_array($expected) && \in_array(self::asString($value), array_map(self::asString(...), $expected), true),
            FilterOperator::Like => \is_string($value) && \is_string($expected) && false !== stripos($value, $expected),
            // Operators not supported by Redis hash matching (Gt/Lt/Between/etc.)
            // — don't constrain so the rest of the query still works.
            default => true,
        };
    }

    private static function looseEquals(mixed $value, mixed $expected): bool
    {
        if (\is_bool($expected)) {
            return self::asBool($value) === $expected;
        }

        return self::asString($value) === self::asString($expected);
    }

    private static function asString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }

    private static function asBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private static function extractId(DataPayload $payload): string
    {
        $id = $payload->get('id');
        if (!\is_string($id) && !\is_int($id)) {
            throw new RuntimeException('RedisHashDataSource: payload must carry a non-empty "id" property.');
        }
        $id = (string) $id;
        if ('' === $id) {
            throw new RuntimeException('RedisHashDataSource: payload "id" cannot be empty.');
        }

        return $id;
    }

    /**
     * @return array<string, string>
     */
    private static function stringifyFields(DataPayload $payload): array
    {
        $fields = [];
        foreach ($payload->properties as $key => $value) {
            if ('id' === $key) {
                continue; // id lives in the Redis key, not as a hash field
            }
            if (\is_bool($value)) {
                $fields[$key] = $value ? '1' : '0';

                continue;
            }
            if (null === $value) {
                $fields[$key] = '';

                continue;
            }
            if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
                $fields[$key] = (string) $value;

                continue;
            }
            $fields[$key] = (string) json_encode($value, \JSON_THROW_ON_ERROR);
        }

        return $fields;
    }
}
