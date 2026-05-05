<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\DataSource;

use Polysource\Adapter\Redis\Client\RedisHashClientInterface;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
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
 * Cf. ADR-002 — `count()` returns `null`. SCAN does not give us a
 * cheap exact count, and the `KEYS` command is forbidden in
 * production (O(n) blocking). The UI uses cursor pagination
 * driven by `nextCursor` / `prevCursor`.
 *
 * Filter mapping in v0.1 is **client-side** — Redis hashes don't
 * support server-side filtering; we'd need RediSearch for that.
 * `search()` materialises a page and filters in-process. For
 * collections of more than a few thousand records, hosts run their
 * own RediSearch index and ship a custom data source.
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
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;

        // Cursor pagination: SCAN returns its own cursor — we surface
        // it as DataPage::$nextCursor. The DataQuery's offset is
        // ignored (cursor-based sources can't honour offset cheaply).
        $cursor = self::cursorFromQuery($pagination);
        $records = [];
        $iterations = 0;
        $reachedLimit = false;
        $lastScanCursor = $cursor;

        do {
            [$nextCursor, $keys] = $this->client->scan($cursor, $this->keyPrefix . '*', max($limit * 2, 100));
            $lastScanCursor = $nextCursor;

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
                $records[] = $record;

                if (\count($records) >= $limit) {
                    $reachedLimit = true;

                    break 2;
                }
            }

            $cursor = $nextCursor;
            ++$iterations;
        } while ('0' !== $cursor && $iterations < 50); // hard cap against pathological scans

        // When we stopped because we hit the limit, expose the cursor
        // from the most recent scan so the caller can resume there.
        // Redis SCAN tolerates duplicate keys across pages.
        $pageNextCursor = $reachedLimit
            ? ('0' === $lastScanCursor ? null : $lastScanCursor)
            : ('0' === $cursor ? null : $cursor);

        return new DataPage(
            items: $records,
            total: null,
            nextCursor: $pageNextCursor,
            prevCursor: null === $pagination ? null : self::cursorFromQuery($pagination),
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

    private static function cursorFromQuery(?\Polysource\Core\Query\Pagination $pagination): string
    {
        if (null === $pagination) {
            return '0';
        }

        // Pagination::offset is misused as a cursor token here — the
        // UI builds it from the previous response's nextCursor. This
        // is a minor protocol abuse but avoids an extra type for v0.1.
        return 0 === $pagination->offset ? '0' : (string) $pagination->offset;
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

    private static function matchesCriterion(mixed $value, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq' => self::looseEquals($value, $expected),
            'neq' => !self::looseEquals($value, $expected),
            'in' => \is_array($expected) && \in_array(self::asString($value), array_map(self::asString(...), $expected), true),
            'like' => \is_string($value) && \is_string($expected) && false !== stripos($value, $expected),
            default => true, // unsupported operator — don't constrain
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
