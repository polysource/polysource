<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Client;

/**
 * Minimal Redis hash client surface used by the Polysource Redis
 * adapter — abstracted to keep the package decoupled from any
 * specific Redis library (Predis, ext-redis, …).
 *
 * Only the 5 commands actually exercised are exposed. Hosts ship a
 * thin adapter to whatever client they already have configured —
 * see `PredisRedisHashClient` below for the canonical Predis
 * implementation.
 *
 * Why a custom interface rather than typing on Predis directly:
 *
 *  - Predis' `ClientInterface` carries 100+ methods (magic dispatch
 *    over every Redis command). Typing on it pulls the whole API
 *    surface into our own contract — overkill for our needs.
 *  - Hosts on ext-redis (no Predis) can satisfy this contract with
 *    a 30-LOC wrapper without installing Predis.
 *  - In-memory test fakes implement only this surface (cf.
 *    `InMemoryRedisHashClient` in tests).
 */
interface RedisHashClientInterface
{
    /**
     * Iterate keys matching the given pattern.
     *
     * Returns `[$nextCursor, $matchingKeys]`. `$nextCursor === '0'`
     * signals end of iteration.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function scan(string $cursor, string $pattern, int $count = 100): array;

    /**
     * Return the full hash stored at `$key` as a `field => value`
     * map, or an empty array when the key does not exist.
     *
     * @return array<string, string>
     */
    public function hgetall(string $key): array;

    /**
     * Set every `field => value` in the hash. Overwrites existing
     * fields; preserves fields not present in `$fields`. The host's
     * application is expected to wipe the key first when full
     * replacement is intended.
     *
     * @param array<string, string> $fields
     */
    public function hmset(string $key, array $fields): void;

    /**
     * Delete the given key. Idempotent — deleting a non-existent
     * key is a no-op.
     */
    public function del(string $key): void;

    /**
     * Whether the given key currently exists.
     */
    public function exists(string $key): bool;
}
