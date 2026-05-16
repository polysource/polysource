<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\InMemory;

/**
 * @deprecated since 0.8.0 — use {@see InMemoryRedisClient} instead.
 *             v0.8.0 expanded the fake to all 5 Redis types. Kept as
 *             an alias so existing test files keep compiling. Will be
 *             removed at v1.0.
 */
final class InMemoryRedisHashClient extends InMemoryRedisClient
{
    /**
     * Legacy seeding method — forwards to {@see InMemoryRedisClient::seedHash}.
     *
     * @param array<string, string> $fields
     */
    public function seed(string $key, array $fields): void
    {
        $this->seedHash($key, $fields);
    }
}
