<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Client;

use Predis\ClientInterface;

/**
 * Default {@see RedisHashClientInterface} implementation wrapping
 * Predis. Hosts that already wire `Predis\Client` get the adapter
 * for free by aliasing this class to the interface.
 *
 * Predis is a soft dependency — this class is only loaded when the
 * host actually uses it. Apps on ext-redis ship their own adapter
 * implementing the same 5-method contract.
 */
final class PredisRedisHashClient implements RedisHashClientInterface
{
    public function __construct(private readonly ClientInterface $client)
    {
    }

    public function scan(string $cursor, string $pattern, int $count = 100): array
    {
        /** @var array{0: int|string, 1: list<string>} $result */
        $result = $this->client->scan((int) $cursor, ['MATCH' => $pattern, 'COUNT' => $count]);

        return [(string) $result[0], $result[1]];
    }

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

    public function del(string $key): void
    {
        $this->client->del([$key]);
    }

    public function exists(string $key): bool
    {
        return (bool) $this->client->exists($key);
    }
}
