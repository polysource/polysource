<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Client;

/**
 * Surface of Redis commands consumed by the polysource/adapter-redis
 * data sources — covers all 5 Redis data types (string, list, hash,
 * set, sorted-set) plus the 5 cross-type primitives (scan, exists,
 * del, type, ttl).
 *
 * Why a custom interface rather than typing on Predis directly:
 *
 *  - Predis' `ClientInterface` carries 100+ methods (magic dispatch
 *    over every Redis command) — typing on it would pull the whole
 *    Redis surface into our own contract.
 *  - Hosts on ext-redis (no Predis) can satisfy this contract with
 *    a 30-LOC wrapper.
 *  - In-memory test fakes implement only this surface (cf.
 *    `InMemoryRedisClient` in tests).
 *
 * **21 methods** grouped by Redis data type. ISP critics could argue
 * for 5 sub-interfaces; in practice a Redis client always exposes
 * all commands so the split would be ceremony with no consumer
 * benefit.
 *
 * @since 0.8.0
 */
interface RedisClientInterface
{
    // ─── Cross-type primitives ─────────────────────────────────────

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
     * Whether the given key currently exists.
     */
    public function exists(string $key): bool;

    /**
     * Delete the given key. Idempotent — deleting a non-existent
     * key is a no-op.
     */
    public function del(string $key): void;

    /**
     * Return the Redis data type of `$key` — one of `'string'`,
     * `'list'`, `'hash'`, `'set'`, `'zset'`, or `'none'` when the
     * key doesn't exist.
     */
    public function type(string $key): string;

    /**
     * Return the remaining TTL in seconds, `-1` for no expiry, or
     * `-2` when the key doesn't exist.
     */
    public function ttl(string $key): int;

    // ─── String type ───────────────────────────────────────────────

    /**
     * Return the string value at `$key`, or `null` if the key is
     * unset or holds a non-string type.
     */
    public function get(string $key): ?string;

    /**
     * Set the string value at `$key`. Optional TTL in seconds.
     */
    public function set(string $key, string $value, ?int $ttlSeconds = null): void;

    // ─── List type ─────────────────────────────────────────────────

    /**
     * Return the length of the list at `$key`, or `0` when the key
     * doesn't exist.
     */
    public function llen(string $key): int;

    /**
     * Return the slice `[start, stop]` (inclusive, both ends) of the
     * list at `$key`. Negative indexes count from the tail (`-1` is
     * the last element).
     *
     * @return list<string>
     */
    public function lrange(string $key, int $start, int $stop): array;

    /**
     * Push `$values` onto the right end of the list at `$key`.
     *
     * @param list<string> $values
     */
    public function rpush(string $key, array $values): void;

    /**
     * Pop and return the leftmost element of the list at `$key`, or
     * `null` when the list is empty / the key doesn't exist.
     */
    public function lpop(string $key): ?string;

    // ─── Hash type ─────────────────────────────────────────────────

    /**
     * Return the full hash stored at `$key` as a `field => value`
     * map, or an empty array when the key doesn't exist.
     *
     * @return array<string, string>
     */
    public function hgetall(string $key): array;

    /**
     * Set every `field => value` in the hash. Overwrites existing
     * fields; preserves fields not present in `$fields`. Hosts that
     * want full replacement wipe the key first.
     *
     * @param array<string, string> $fields
     */
    public function hmset(string $key, array $fields): void;

    // ─── Set type ──────────────────────────────────────────────────

    /**
     * Return every member of the set at `$key`, or `[]` when the key
     * doesn't exist.
     *
     * @return list<string>
     */
    public function smembers(string $key): array;

    /**
     * Add `$members` to the set at `$key`. Existing members are kept.
     *
     * @param list<string> $members
     */
    public function sadd(string $key, array $members): void;

    /**
     * Remove `$members` from the set at `$key`. Idempotent.
     *
     * @param list<string> $members
     */
    public function srem(string $key, array $members): void;

    /**
     * Return the cardinality of the set at `$key`, or `0` when the
     * key doesn't exist.
     */
    public function scard(string $key): int;

    // ─── Sorted set type ───────────────────────────────────────────

    /**
     * Return the slice `[start, stop]` of the sorted set at `$key`,
     * ordered ascending by score, with scores attached as a
     * `member => score` map.
     *
     * @return array<string, float>
     */
    public function zrange(string $key, int $start, int $stop): array;

    /**
     * Add `$scoredMembers` (`member => score`) to the sorted set at
     * `$key`. Updates the score of existing members.
     *
     * @param array<string, float> $scoredMembers
     */
    public function zadd(string $key, array $scoredMembers): void;

    /**
     * Remove `$members` from the sorted set at `$key`. Idempotent.
     *
     * @param list<string> $members
     */
    public function zrem(string $key, array $members): void;

    /**
     * Return the cardinality of the sorted set at `$key`, or `0`
     * when the key doesn't exist.
     */
    public function zcard(string $key): int;
}
