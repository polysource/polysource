<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\InMemory;

use Polysource\Adapter\Redis\Client\RedisHashClientInterface;

/**
 * Test-only {@see RedisHashClientInterface} backed by an in-process
 * map. Implements just enough of Redis hash semantics to exercise
 * the data source without spinning a real Redis server.
 *
 * SCAN emulation: iterates the key map in insertion order, slicing
 * by the cursor (= offset). Pattern matching uses fnmatch().
 */
final class InMemoryRedisHashClient implements RedisHashClientInterface
{
    /** @var array<string, array<string, string>> */
    private array $hashes = [];

    public function scan(string $cursor, string $pattern, int $count = 100): array
    {
        // Mimic real Redis SCAN: visit at most `$count` keys per call,
        // returning whatever subset of those matches the pattern. The
        // cursor advances by `$count` regardless of how many matched —
        // this is what forces multi-iteration behaviour in the data
        // source when the dataset is larger than one batch.
        $allKeys = array_keys($this->hashes);
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

    public function del(string $key): void
    {
        unset($this->hashes[$key]);
    }

    public function exists(string $key): bool
    {
        return isset($this->hashes[$key]);
    }

    /**
     * Test-only seeding hook.
     *
     * @param array<string, string> $fields
     */
    public function seed(string $key, array $fields): void
    {
        $this->hashes[$key] = $fields;
    }
}
