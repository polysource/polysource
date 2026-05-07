# polysource/adapter-redis

> Redis hashes adapter for Polysource — admin feature flags, rate-limit buckets, in-memory caches, any key/value or hash collection.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`RedisHashClientInterface`** (5 methods) — abstraction over the Redis client used by the data source. Decouples from Predis/PhpRedis specifics.
- **`PredisRedisHashAdapter`** — production implementation against `predis/predis`.
- **`InMemoryRedisHashFake`** — test double, no Redis needed.
- **`RedisHashDataSource`** — implements `WritableDataSourceInterface` with SCAN cursor pagination (no `KEYS` calls) and client-side filtering.
- **`RedisHashResource`** — non-final convenience base.

## Install

```bash
composer require polysource/adapter-redis predis/predis
```

Register the bundle:

```php
return [
    Polysource\Adapter\Redis\PolysourceAdapterRedisBundle::class => ['all' => true],
];
```

## Documentation

- [Adapter redis guide](../../docs/user/adapters/redis.md)
