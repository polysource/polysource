<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\DataSource;

use InvalidArgumentException;
use Polysource\Adapter\Redis\Client\RedisClientInterface;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterOperator;

/**
 * Read+write data source over a namespaced collection of Redis
 * string keys — typical use: cache entries, session payloads,
 * simple feature toggles.
 *
 * Each record's identifier is the suffix of the key after `$keyPrefix`,
 * and the record's `value` property carries the raw string. TTL is
 * exposed via the `ttl` property (seconds, -1 = no expiry, -2 = key
 * vanished since SCAN).
 *
 * Pagination: same offset/limit pattern as {@see RedisHashDataSource}.
 * `search()` materialises every matching key via repeated SCAN until
 * the cursor exhausts (capped at 50 iterations × 100 = 5000 keys),
 * sorts by id for stable ordering, then slices in memory.
 *
 * Type-safety: keys returned by SCAN that aren't of type `string`
 * are silently skipped — prevents `WRONGTYPE` errors when the host's
 * `$keyPrefix` overlaps with non-string keys (a common situation
 * during dogfooding when a workflow pushes to a `prefix:queue:*`
 * list inside the same namespace).
 *
 * Filter mapping in v0.1 is **client-side via SCAN MATCH pattern**:
 * the criterion's value joins the SCAN MATCH glob.
 *
 * @since 0.8.0
 */
final class RedisStringDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public function __construct(
        private readonly RedisClientInterface $client,
        private readonly string $keyPrefix,
        private readonly int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        $pagination = $query->pagination;
        $offset = null === $pagination ? 0 : $pagination->offset;
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;

        $records = [];
        $cursor = '0';
        $iterations = 0;
        do {
            [$cursor, $keys] = $this->client->scan($cursor, $this->scanPattern($query), 100);
            foreach ($keys as $key) {
                if ('string' !== $this->client->type($key)) {
                    continue;
                }
                $id = substr($key, \strlen($this->keyPrefix));
                if ('' === $id) {
                    continue;
                }
                $records[] = $this->buildRecord($id, $key);
            }
            ++$iterations;
        } while ('0' !== $cursor && $iterations < 50);

        usort($records, static fn (DataRecord $a, DataRecord $b): int => strcmp((string) $a->identifier, (string) $b->identifier));
        $total = \count($records);
        $slice = array_values(\array_slice($records, $offset, $limit));

        return new DataPage(items: $slice, total: $total);
    }

    public function find(int|string $identifier): ?DataRecord
    {
        $key = $this->keyPrefix . (string) $identifier;
        if ('string' !== $this->client->type($key)) {
            return null;
        }

        return $this->buildRecord((string) $identifier, $key);
    }

    public function count(DataQuery $query): ?int
    {
        unset($query);

        return null;
    }

    public function create(DataPayload $payload): DataRecord
    {
        $id = $this->requireStringProp($payload, 'id');
        $value = $this->requireStringProp($payload, 'value');
        $ttl = $this->extractTtl($payload);

        $this->client->set($this->keyPrefix . $id, $value, $ttl);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $id = (string) $identifier;
        $value = $this->requireStringProp($payload, 'value');
        $ttl = $this->extractTtl($payload);

        $this->client->set($this->keyPrefix . $id, $value, $ttl);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function delete(int|string $identifier): void
    {
        $this->client->del($this->keyPrefix . (string) $identifier);
    }

    private function buildRecord(string $id, string $key): DataRecord
    {
        return new DataRecord(
            identifier: $id,
            properties: [
                'id' => $id,
                'value' => $this->client->get($key) ?? '',
                'ttl' => $this->client->ttl($key),
            ],
        );
    }

    private function scanPattern(DataQuery $query): string
    {
        $criterion = $query->filters['id'] ?? null;
        if (null === $criterion) {
            return $this->keyPrefix . '*';
        }
        $op = $criterion->operator;
        $isLike = $op instanceof FilterOperator ? FilterOperator::Like === $op : 'like' === (string) $op;
        if (!$isLike || !\is_string($criterion->value) || '' === $criterion->value) {
            return $this->keyPrefix . '*';
        }
        $glob = $criterion->value;
        if (!str_contains($glob, '*') && !str_contains($glob, '?')) {
            $glob = '*' . $glob . '*';
        }

        return $this->keyPrefix . $glob;
    }

    private function requireStringProp(DataPayload $payload, string $key): string
    {
        $value = $payload->properties[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            throw new InvalidArgumentException(\sprintf('RedisStringDataSource payload requires non-empty string `%s`.', $key));
        }

        return $value;
    }

    private function extractTtl(DataPayload $payload): ?int
    {
        $value = $payload->properties['ttl'] ?? null;
        if (\is_int($value) && $value > 0) {
            return $value;
        }
        if (\is_string($value) && ctype_digit($value)) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        return null;
    }
}
