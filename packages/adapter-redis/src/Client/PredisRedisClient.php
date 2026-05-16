<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Client;

use Predis\ClientInterface;
use Stringable;

/**
 * Default {@see RedisClientInterface} implementation wrapping Predis
 * — covers all 5 Redis data types.
 *
 * Predis is a soft dependency — this class is only loaded when the
 * host actually uses it. Apps on ext-redis ship their own adapter
 * implementing the same 21-method contract.
 *
 * @since 0.8.0 expanded from hash-only to all 5 types
 */
class PredisRedisClient implements RedisClientInterface
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    // ─── Cross-type ────────────────────────────────────────────────

    public function scan(string $cursor, string $pattern, int $count = 100): array
    {
        /** @var array{0: int|string, 1: list<string>} $result */
        $result = $this->client->scan((int) $cursor, ['MATCH' => $pattern, 'COUNT' => $count]);

        return [(string) $result[0], $result[1]];
    }

    public function exists(string $key): bool
    {
        return (bool) $this->client->exists($key);
    }

    public function del(string $key): void
    {
        $this->client->del([$key]);
    }

    public function type(string $key): string
    {
        // Predis returns the type as a `Status` object whose
        // `__toString()` yields the type name ('string', 'list', …).
        // The magic-dispatch return is `mixed`, hence the explicit
        // narrowing.
        $type = $this->client->type($key);
        if ($type instanceof Stringable) {
            return (string) $type;
        }

        return \is_string($type) ? $type : 'none';
    }

    public function ttl(string $key): int
    {
        return (int) $this->client->ttl($key);
    }

    // ─── String ────────────────────────────────────────────────────

    public function get(string $key): ?string
    {
        /** @var string|null $value */
        $value = $this->client->get($key);

        return null === $value ? null : (string) $value;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): void
    {
        if (null === $ttlSeconds) {
            $this->client->set($key, $value);

            return;
        }
        // Predis SET with `EX` flag — Redis ≥ 2.6.12 syntax, supported
        // by every Predis version in the polysource matrix.
        $this->client->set($key, $value, 'EX', $ttlSeconds);
    }

    // ─── List ──────────────────────────────────────────────────────

    public function llen(string $key): int
    {
        return (int) $this->client->llen($key);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        /** @var list<string> $items */
        $items = $this->client->lrange($key, $start, $stop);

        return $items;
    }

    public function rpush(string $key, array $values): void
    {
        if ([] === $values) {
            return;
        }
        $this->client->rpush($key, $values);
    }

    public function lpop(string $key): ?string
    {
        /** @var string|null $popped */
        $popped = $this->client->lpop($key);

        return null === $popped ? null : (string) $popped;
    }

    // ─── Hash ──────────────────────────────────────────────────────

    public function hgetall(string $key): array
    {
        /** @var array<string, string> $hash */
        $hash = $this->client->hgetall($key);

        return $hash;
    }

    public function hmset(string $key, array $fields): void
    {
        if ([] === $fields) {
            return;
        }
        $this->client->hmset($key, $fields);
    }

    // ─── Set ───────────────────────────────────────────────────────

    public function smembers(string $key): array
    {
        /** @var list<string> $members */
        $members = $this->client->smembers($key);

        return $members;
    }

    public function sadd(string $key, array $members): void
    {
        if ([] === $members) {
            return;
        }
        $this->client->sadd($key, $members);
    }

    public function srem(string $key, array $members): void
    {
        if ([] === $members) {
            return;
        }
        $this->client->srem($key, $members);
    }

    public function scard(string $key): int
    {
        return (int) $this->client->scard($key);
    }

    // ─── Sorted set ────────────────────────────────────────────────

    public function zrange(string $key, int $start, int $stop): array
    {
        /** @var array<string, float> $entries */
        $entries = $this->client->zrange($key, $start, $stop, ['WITHSCORES' => true]);

        // Predis returns scores as native floats already; cast defensively
        // because some Redis server versions emit them as strings.
        $normalised = [];
        foreach ($entries as $member => $score) {
            $normalised[(string) $member] = (float) $score;
        }

        return $normalised;
    }

    public function zadd(string $key, array $scoredMembers): void
    {
        if ([] === $scoredMembers) {
            return;
        }
        // Predis' ZADD signature: zadd($key, ['member' => score])
        $this->client->zadd($key, $scoredMembers);
    }

    public function zrem(string $key, array $members): void
    {
        if ([] === $members) {
            return;
        }
        // Predis's ZREM is variadic: `zrem(string $key, string ...$members)`.
        // Spread the array to match the signature.
        $this->client->zrem($key, ...$members);
    }

    public function zcard(string $key): int
    {
        return (int) $this->client->zcard($key);
    }
}
