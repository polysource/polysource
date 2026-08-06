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

/**
 * Read+write data source over a namespaced collection of Redis lists
 * — typical use: workflow queues, event streams, recent activity
 * tails, retry buffers.
 *
 * Each record represents one list key. The identifier is the suffix
 * after `$keyPrefix`; properties expose:
 *
 *   - `length` (int) — current `LLEN`
 *   - `head` (list<string>) — first N items (default 5) via `LRANGE 0 N-1`
 *   - `headPreview` (string) — newline-joined head for textual rendering
 *   - `ttl` (int) — seconds, -1 = no expiry
 *
 * Writes:
 *   - `create()` accepts an `items: list<string>` property and
 *     `RPUSH`es every value (the list itself is created on first
 *     push in Redis semantics).
 *   - `update()` is treated as "append" (`RPUSH`) — partial mutations
 *     on lists don't map cleanly to a `DataPayload` shape; hosts
 *     wanting LPUSH / LSET semantics ship a custom Action.
 *   - `delete()` removes the entire list key. Pop-one is exposed
 *     through {@see RedisClientInterface::lpop} for hosts that wire
 *     a custom Action.
 *
 * Filter mapping: SCAN MATCH pattern on the key identifier (same as
 * the string adapter).
 *
 * @since 0.8.0
 */
final class RedisListDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public const DEFAULT_HEAD_LIMIT = 5;

    public function __construct(
        private readonly RedisClientInterface $client,
        private readonly string $keyPrefix,
        private readonly int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
        private readonly int $headLimit = self::DEFAULT_HEAD_LIMIT,
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
                if ('list' !== $this->client->type($key)) {
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
        if ('list' !== $this->client->type($key)) {
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
        $items = $this->requireItemsProp($payload);

        $this->client->rpush($this->keyPrefix . $id, $items);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $id = (string) $identifier;
        $items = $this->requireItemsProp($payload);

        $this->client->rpush($this->keyPrefix . $id, $items);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function delete(int|string $identifier): void
    {
        $this->client->del($this->keyPrefix . (string) $identifier);
    }

    private function buildRecord(string $id, string $key): DataRecord
    {
        $length = $this->client->llen($key);
        $head = $this->client->lrange($key, 0, $this->headLimit - 1);

        return new DataRecord(
            identifier: $id,
            properties: [
                'id' => $id,
                'length' => $length,
                'head' => $head,
                'headPreview' => implode("\n", $head),
                'ttl' => $this->client->ttl($key),
            ],
        );
    }

    private function scanPattern(DataQuery $query): string
    {
        return ScanPatternResolver::resolve($query, $this->keyPrefix);
    }

    private function requireStringProp(DataPayload $payload, string $key): string
    {
        $value = $payload->properties[$key] ?? null;
        if (!\is_string($value) || '' === $value) {
            throw new InvalidArgumentException(\sprintf('RedisListDataSource payload requires non-empty string `%s`.', $key));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function requireItemsProp(DataPayload $payload): array
    {
        $items = $payload->properties['items'] ?? null;
        if (!\is_array($items) || [] === $items) {
            throw new InvalidArgumentException('RedisListDataSource payload requires non-empty `items: list<string>`.');
        }
        $normalised = [];
        foreach ($items as $item) {
            if (!\is_scalar($item)) {
                throw new InvalidArgumentException('RedisListDataSource `items` must contain scalar values only.');
            }
            $normalised[] = (string) $item;
        }

        return $normalised;
    }
}
