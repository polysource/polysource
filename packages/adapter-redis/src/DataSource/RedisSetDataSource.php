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
 * Read+write data source over a namespaced collection of Redis sets
 * — typical use: unique-tracking collections (online users, blocked
 * IPs, distinct tags, deduplication caches).
 *
 * Each record represents one set key. Properties:
 *
 *   - `cardinality` (int) — `SCARD`
 *   - `members` (list<string>) — full set (warning: O(N), no
 *     pagination of members within a key — use a sorted set if you
 *     have very large sets and need member-level iteration).
 *   - `ttl` (int) — seconds
 *
 * Writes:
 *   - `create()` / `update()` SADD `members: list<string>` from the
 *     payload (additive — set semantics make "replace whole set"
 *     ambiguous; hosts wanting that ship a custom Action that calls
 *     `del` then `sadd`).
 *   - `delete()` drops the entire key.
 *
 * @since 0.8.0
 */
final class RedisSetDataSource implements WritableDataSourceInterface
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
                if ('set' !== $this->client->type($key)) {
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
        if ('set' !== $this->client->type($key)) {
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
        $members = $this->requireMembersProp($payload);

        $this->client->sadd($this->keyPrefix . $id, $members);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $id = (string) $identifier;
        $members = $this->requireMembersProp($payload);

        $this->client->sadd($this->keyPrefix . $id, $members);

        return $this->buildRecord($id, $this->keyPrefix . $id);
    }

    public function delete(int|string $identifier): void
    {
        $this->client->del($this->keyPrefix . (string) $identifier);
    }

    private function buildRecord(string $id, string $key): DataRecord
    {
        $members = $this->client->smembers($key);

        return new DataRecord(
            identifier: $id,
            properties: [
                'id' => $id,
                'cardinality' => $this->client->scard($key),
                'members' => $members,
                'membersPreview' => implode("\n", $members),
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
            throw new InvalidArgumentException(\sprintf('RedisSetDataSource payload requires non-empty string `%s`.', $key));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function requireMembersProp(DataPayload $payload): array
    {
        $members = $payload->properties['members'] ?? null;
        if (!\is_array($members) || [] === $members) {
            throw new InvalidArgumentException('RedisSetDataSource payload requires non-empty `members: list<string>`.');
        }
        $normalised = [];
        foreach ($members as $member) {
            if (!\is_scalar($member)) {
                throw new InvalidArgumentException('RedisSetDataSource `members` must contain scalar values only.');
            }
            $normalised[] = (string) $member;
        }

        return $normalised;
    }
}
