<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Client;

/**
 * @deprecated since 0.8.0 — use {@see RedisClientInterface} instead.
 *             v0.8.0 expanded the surface from hashes only to all 5
 *             Redis types. This interface is kept as a sub-set view
 *             for backward compatibility but will be removed at v1.0.
 *
 * Inherits the full 21-method surface so existing implementations
 * (typically `PredisRedisClient` aliased to this interface in DI)
 * keep working — they were already passing the methods this
 * interface declared and now satisfy the wider parent contract too.
 */
interface RedisHashClientInterface extends RedisClientInterface
{
}
