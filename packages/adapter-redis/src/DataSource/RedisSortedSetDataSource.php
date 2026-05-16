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
 * sorted sets — typical use: leaderboards, time-series buckets,
 * priority queues, rate-limit windows.
 *
 * Each record represents one zset key. Properties:
 *
 *   - `cardinality` (int) — `ZCARD`
 *   - `topMembers` (array<string, float>) — first N entries (default
 *     5) ascending by score via `ZRANGE 0 N-1 WITHSCORES`
 *   - `topPreview` (string) — newline-joined `member=score` for
 *     textual rendering
 *   - `ttl` (int)
 *
 * Writes:
 *   - `create()` / `update()` ZADD a `scoredMembers: array<string, float>`
 *     payload (additive — updates existing member's score, adds new ones).
 *   - `delete()` drops the entire zset key.
 *
 * For per-member operations (remove one entry, change score),
 * hosts wire a custom Action that calls `zrem` / `zadd` directly.
 *
 * @since 0.8.0
 */
final class RedisSortedSetDataSource implements WritableDataSourceInterface
{
    public const DEFAULT_PAGE_SIZE = 50;

    public const DEFAULT_TOP_LIMIT = 5;

    public function __construct(
        private readonly RedisClientInterface $client,
        private readonly string $keyPrefix,
        private readonly int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
        private readonly int $topLimit = self::DEFAULT_TOP_LIMIT,
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
                if ('zset' !== $this->client->type($key)) {
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
        if ('zset' !== $this->client->type($key)) {
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
        $scored = $this->requireScoredMembers($payload);

        $this->client->zadd($this->keyPrefix . $id, $scored);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $id = (string) $identifier;
        $scored = $this->requireScoredMembers($payload);

        $this->client->zadd($this->keyPrefix . $id, $scored);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function delete(int|string $identifier): void
    {
        $this->client->del($this->keyPrefix . (string) $identifier);
    }

    private function buildRecord(string $id, string $key): DataRecord
    {
        $top = $this->client->zrange($key, 0, $this->topLimit - 1);

        $lines = [];
        foreach ($top as $member => $score) {
            $lines[] = $member . '=' . $score;
        }

        return new DataRecord(
            identifier: $id,
            properties: [
                'id' => $id,
                'cardinality' => $this->client->zcard($key),
                'topMembers' => $top,
                'topPreview' => implode("\n", $lines),
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
            throw new InvalidArgumentException(\sprintf('RedisSortedSetDataSource payload requires non-empty string `%s`.', $key));
        }

        return $value;
    }

    /**
     * @return array<string, float>
     */
    private function requireScoredMembers(DataPayload $payload): array
    {
        $entries = $payload->properties['scoredMembers'] ?? null;
        if (!\is_array($entries) || [] === $entries) {
            throw new InvalidArgumentException('RedisSortedSetDataSource payload requires non-empty `scoredMembers: array<string, float>`.');
        }
        $normalised = [];
        foreach ($entries as $member => $score) {
            // PHP array keys are always int|string by construction.
            if (!is_numeric($score)) {
                throw new InvalidArgumentException(\sprintf('RedisSortedSetDataSource score for member `%s` must be numeric.', (string) $member));
            }
            $normalised[(string) $member] = (float) $score;
        }

        return $normalised;
    }
}
