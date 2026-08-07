# polysource/adapter-redis

> Redis adapter for Polysource — admin feature flags, rate-limit buckets, workflow queues, leaderboards, cache entries. Covers all five Redis data types, one type-pure data source each.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`RedisClientInterface`** (21 methods) — abstraction over the Redis client used by the data sources, spanning the five supported types. Decouples from Predis/PhpRedis specifics. `RedisHashClientInterface` is the historical hashes-only name, kept as a deprecated alias that now extends the wider contract.
- **`PredisRedisClient`** — production implementation against `predis/predis`. `PredisRedisHashClient` is its hashes-scoped counterpart.
- **Five type-pure data sources**, each implementing `WritableDataSourceInterface` over a key prefix and filtering client-side. `search()` walks the keyspace with repeated `SCAN` (never `KEYS`), sorts by id for stable ordering, then slices offset/limit in memory — the scan is capped at 50 iterations (~5 000 keys per namespace), so these are admin-sized collections, not unbounded ones:

  | Data source | One record is | Exposed properties |
  |---|---|---|
  | `RedisHashDataSource` | one hash key | the hash fields themselves |
  | `RedisListDataSource` | one list key | `length`, `head`, `headPreview`, `ttl` |
  | `RedisSetDataSource` | one set key | `cardinality`, `members`, `ttl` |
  | `RedisSortedSetDataSource` | one sorted-set key | `cardinality`, `topMembers`, `topPreview`, `ttl` |
  | `RedisStringDataSource` | one string key | `value`, `ttl` |

- **Five matching resources** — `RedisHashResource`, `RedisListResource`, `RedisSetResource`, `RedisSortedSetResource`, `RedisStringResource`: non-final convenience bases, one subclass per Redis namespace you want to admin.

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

- [Adapter redis guide](https://github.com/polysource/polysource/blob/main/docs/user/adapters/redis.md)
