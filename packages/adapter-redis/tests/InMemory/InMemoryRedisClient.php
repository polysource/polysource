<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\InMemory;

use Polysource\Adapter\Redis\Client\RedisClientInterface;

/**
 * Test-only {@see RedisClientInterface} backed by in-process maps.
 * Implements just enough of Redis semantics across all 5 data types
 * to exercise the type-specific data sources without spinning a real
 * Redis server.
 *
 * SCAN emulation: iterates the union of all keys in insertion order,
 * slicing by the cursor (= offset). Pattern matching uses fnmatch().
 *
 * @since 0.8.0 expanded from hash-only to all 5 Redis types
 */
class InMemoryRedisClient implements RedisClientInterface
{
    /** @var array<string, string> */
    private array $strings = [];

    /** @var array<string, list<string>> */
    private array $lists = [];

    /** @var array<string, array<string, string>> */
    private array $hashes = [];

    /** @var array<string, list<string>> internal: list-of-unique members */
    private array $sets = [];

    /** @var array<string, array<string, float>> */
    private array $zsets = [];

    /** @var array<string, int> remaining seconds, or -1 for no expiry */
    private array $ttls = [];

    public function scan(string $cursor, string $pattern, int $count = 100): array
    {
        $allKeys = $this->allKeys();
        $totalKeys = \count($allKeys);
        $offset = (int) $cursor;
        $end = min($offset + $count, $totalKeys);

        $matched = [];
        for ($i = $offset; $i < $end; ++$i) {
            if (fnmatch($pattern, $allKeys[$i])) {
                $matched[] = $allKeys[$i];
            }
        }

        $next = $end >= $totalKeys ? '0' : (string) $end;

        return [$next, $matched];
    }

    public function exists(string $key): bool
    {
        return 'none' !== $this->type($key);
    }

    public function del(string $key): void
    {
        unset($this->strings[$key], $this->lists[$key], $this->hashes[$key], $this->sets[$key], $this->zsets[$key], $this->ttls[$key]);
    }

    public function type(string $key): string
    {
        return match (true) {
            isset($this->strings[$key]) => 'string',
            isset($this->lists[$key]) => 'list',
            isset($this->hashes[$key]) => 'hash',
            isset($this->sets[$key]) => 'set',
            isset($this->zsets[$key]) => 'zset',
            default => 'none',
        };
    }

    public function ttl(string $key): int
    {
        if (!$this->exists($key)) {
            return -2;
        }

        return $this->ttls[$key] ?? -1;
    }

    public function get(string $key): ?string
    {
        return $this->strings[$key] ?? null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): void
    {
        // Real Redis SET overwrites whatever type is at that key —
        // mirror that behaviour so tests catch host code that relies
        // on type stability across writes.
        unset($this->lists[$key], $this->hashes[$key], $this->sets[$key], $this->zsets[$key]);
        $this->strings[$key] = $value;
        if (null !== $ttlSeconds) {
            $this->ttls[$key] = $ttlSeconds;
        } else {
            unset($this->ttls[$key]);
        }
    }

    public function llen(string $key): int
    {
        return \count($this->lists[$key] ?? []);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        $list = $this->lists[$key] ?? [];
        $length = \count($list);
        if (0 === $length) {
            return [];
        }
        // Normalise negative indexes (Redis: -1 = last element).
        $start = $start < 0 ? max(0, $length + $start) : $start;
        $stop = $stop < 0 ? $length + $stop : $stop;
        $stop = min($stop, $length - 1);
        if ($start > $stop) {
            return [];
        }

        return array_values(\array_slice($list, $start, $stop - $start + 1));
    }

    public function rpush(string $key, array $values): void
    {
        if ([] === $values) {
            return;
        }
        $this->lists[$key] = [...($this->lists[$key] ?? []), ...array_values($values)];
    }

    public function lpop(string $key): ?string
    {
        if (!isset($this->lists[$key]) || [] === $this->lists[$key]) {
            return null;
        }
        $popped = array_shift($this->lists[$key]);
        if ([] === $this->lists[$key]) {
            unset($this->lists[$key]);
        }

        return (string) $popped;
    }

    public function hgetall(string $key): array
    {
        return $this->hashes[$key] ?? [];
    }

    public function hmset(string $key, array $fields): void
    {
        if ([] === $fields) {
            return;
        }
        $existing = $this->hashes[$key] ?? [];
        $this->hashes[$key] = array_merge($existing, $fields);
    }

    public function smembers(string $key): array
    {
        return array_values($this->sets[$key] ?? []);
    }

    public function sadd(string $key, array $members): void
    {
        if ([] === $members) {
            return;
        }
        $existing = $this->sets[$key] ?? [];
        $set = array_values(array_unique([...$existing, ...$members]));
        $this->sets[$key] = $set;
    }

    public function srem(string $key, array $members): void
    {
        if (!isset($this->sets[$key]) || [] === $members) {
            return;
        }
        $this->sets[$key] = array_values(array_diff($this->sets[$key], $members));
        if ([] === $this->sets[$key]) {
            unset($this->sets[$key]);
        }
    }

    public function scard(string $key): int
    {
        return \count($this->sets[$key] ?? []);
    }

    public function zrange(string $key, int $start, int $stop): array
    {
        $zset = $this->zsets[$key] ?? [];
        if ([] === $zset) {
            return [];
        }
        // Sort by score ascending then by member lexicographically
        // (Redis tie-break rule).
        $sorted = $zset;
        uksort($sorted, static function (string $a, string $b) use ($zset): int {
            $cmp = $zset[$a] <=> $zset[$b];

            return 0 !== $cmp ? $cmp : strcmp($a, $b);
        });

        $members = array_keys($sorted);
        $length = \count($members);
        $start = $start < 0 ? max(0, $length + $start) : $start;
        $stop = $stop < 0 ? $length + $stop : $stop;
        $stop = min($stop, $length - 1);
        if ($start > $stop) {
            return [];
        }

        $slice = [];
        for ($i = $start; $i <= $stop; ++$i) {
            $slice[$members[$i]] = $sorted[$members[$i]];
        }

        return $slice;
    }

    public function zadd(string $key, array $scoredMembers): void
    {
        if ([] === $scoredMembers) {
            return;
        }
        $existing = $this->zsets[$key] ?? [];
        foreach ($scoredMembers as $member => $score) {
            $existing[(string) $member] = (float) $score;
        }
        $this->zsets[$key] = $existing;
    }

    public function zrem(string $key, array $members): void
    {
        if (!isset($this->zsets[$key]) || [] === $members) {
            return;
        }
        foreach ($members as $member) {
            unset($this->zsets[$key][$member]);
        }
        if ([] === $this->zsets[$key]) {
            unset($this->zsets[$key]);
        }
    }

    public function zcard(string $key): int
    {
        return \count($this->zsets[$key] ?? []);
    }

    // ─── Test-only seeding hooks ───────────────────────────────────

    public function seedString(string $key, string $value, ?int $ttl = null): void
    {
        $this->strings[$key] = $value;
        if (null !== $ttl) {
            $this->ttls[$key] = $ttl;
        }
    }

    /**
     * @param list<string> $items
     */
    public function seedList(string $key, array $items): void
    {
        $this->lists[$key] = array_values($items);
    }

    /**
     * @param array<string, string> $fields
     */
    public function seedHash(string $key, array $fields): void
    {
        $this->hashes[$key] = $fields;
    }

    /**
     * @param list<string> $members
     */
    public function seedSet(string $key, array $members): void
    {
        $this->sets[$key] = array_values(array_unique($members));
    }

    /**
     * @param array<string, float> $scoredMembers
     */
    public function seedZset(string $key, array $scoredMembers): void
    {
        $this->zsets[$key] = $scoredMembers;
    }

    /**
     * @return list<string>
     */
    private function allKeys(): array
    {
        $keys = [
            ...array_keys($this->strings),
            ...array_keys($this->lists),
            ...array_keys($this->hashes),
            ...array_keys($this->sets),
            ...array_keys($this->zsets),
        ];

        return array_values(array_unique($keys));
    }
}
