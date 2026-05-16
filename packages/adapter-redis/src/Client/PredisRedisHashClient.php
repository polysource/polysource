<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Client;

/**
 * @deprecated since 0.8.0 — use {@see PredisRedisClient} instead.
 *             v0.8.0 expanded the Redis client surface from hashes
 *             only to all 5 Redis types. This subclass is kept as a
 *             drop-in alias for backward compatibility but will be
 *             removed at v1.0.
 *
 * Implementation inherited from {@see PredisRedisClient} — every
 * method this class used to declare is now covered by the parent.
 */
final class PredisRedisHashClient extends PredisRedisClient
{
}
