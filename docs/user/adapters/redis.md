# `polysource/adapter-redis`

Read+write namespaced collections of Redis keys through the
Polysource admin. The adapter ships **five data sources**, one per
Redis type, each paired with a resource base class:

| Data source | Resource base | Each record is | Canonical use |
|---|---|---|---|
| `RedisHashDataSource` | `RedisHashResource` | one hash key | feature flags, key-value config, A/B buckets |
| `RedisStringDataSource` | `RedisStringResource` | one string key | cache entries, session payloads, simple toggles |
| `RedisListDataSource` | `RedisListResource` | one list key | workflow queues, event streams, retry buffers |
| `RedisSetDataSource` | `RedisSetResource` | one set key | online users, blocked IPs, dedup caches |
| `RedisSortedSetDataSource` | `RedisSortedSetResource` | one sorted-set key | leaderboards, time-series buckets, rate-limit windows |

All five implement `WritableDataSourceInterface`, and all five are
scoped by a `$keyPrefix` — every key matching `prefix*` becomes one
record. The string / list / set / sorted-set sources shipped in
v0.8.0; the hash source has been there since the adapter's first
release.

## Install

```bash
composer require polysource/adapter-redis predis/predis
```

```php
// config/bundles.php
return [
    // …
    Polysource\Adapter\Redis\PolysourceAdapterRedisBundle::class => ['all' => true],
];
```

The package autowires two client adapters over any
`Predis\ClientInterface` already present in DI (typically wired by
`SncRedisBundle` or a manual service definition):
`PredisRedisHashClient` implementing `RedisHashClientInterface` (the
hash-only surface), and `PredisRedisClient` implementing the wider
`RedisClientInterface` (`scan`, `get`/`set`, `llen`/`lrange`/`rpush`/
`lpop`, `smembers`/`sadd`/`srem`/`scard`, `zrange`/`zadd`/`zrem`/
`zcard`, plus `exists`, `del`, `type`, `ttl`). Hosts on `ext-redis`
(no Predis) implement whichever of the two interfaces their resources
need.

## Wire a hash resource

```php
use Polysource\Adapter\Redis\Client\RedisHashClientInterface;
use Polysource\Adapter\Redis\DataSource\RedisHashDataSource;
use Polysource\Adapter\Redis\Resource\RedisHashResource;
use Polysource\Bundle\Attribute\AsResource;

#[AsResource]
final class FeatureFlagResource extends RedisHashResource
{
    public function __construct(RedisHashClientInterface $client)
    {
        parent::__construct(
            dataSource: new RedisHashDataSource($client, 'polysource:flag:'),
            slug: 'feature-flags',
            label: 'Feature flags',
            permission: 'POLYSOURCE_FEATURE_FLAG_VIEW',
        );
    }

    public function configureFilters(): iterable
    {
        return [];
    }
}
```

The `keyPrefix` scopes the data source to a namespace — every key
matching `polysource:flag:*` becomes a record. Hosts can stack
several resources with different prefixes (one per namespace), and
mix types freely: a `RedisListResource` on `queue:` next to a
`RedisStringResource` on `PHPREDIS_SESSION:` in the same admin.

The other four resources follow the identical shape — same
constructor signature (`dataSource`, `slug`, `label`,
`identifierProperty = 'id'`, `permission = null`), same `#[AsResource]`
tagging — but take `RedisClientInterface` instead of
`RedisHashClientInterface`:

```php
use Polysource\Adapter\Redis\Client\RedisClientInterface;
use Polysource\Adapter\Redis\DataSource\RedisListDataSource;
use Polysource\Adapter\Redis\Resource\RedisListResource;

#[AsResource]
final class WorkflowQueueResource extends RedisListResource
{
    public function __construct(RedisClientInterface $client)
    {
        parent::__construct(
            dataSource: new RedisListDataSource($client, 'queue:'),
            slug: 'workflow-queues',
            label: 'Workflow queues',
            permission: 'POLYSOURCE_QUEUE_VIEW',
        );
    }
}
```

None of the resource base classes is `final` — override
`configureFields()` / `configureActions()` for your domain without
forking.

## What each data source exposes

Every record's identifier is the key suffix after `$keyPrefix`. The
properties differ by type:

| Source | Properties on each record |
|---|---|
| Hash | the hash fields themselves |
| String | `value` (raw string), `ttl` (seconds; `-1` no expiry, `-2` key vanished since SCAN) |
| List | `length` (`LLEN`), `head` (first N items, default 5), `headPreview` (newline-joined), `ttl` |
| Set | `cardinality` (`SCARD`), `members` (full set — O(N), no member-level pagination), `ttl` |
| Sorted set | `cardinality` (`ZCARD`), `topMembers` (first N ascending by score, default 5), `topPreview`, `ttl` |

Write semantics are type-shaped, and deliberately additive where
"replace the whole thing" would be ambiguous:

| Source | `create()` / `update()` | `delete()` |
|---|---|---|
| Hash | `HMSET` merge — fields absent from the payload are preserved; `create()` refuses an existing key | `DEL` |
| String | `SET` the `value` property | `DEL` |
| List | `RPUSH` an `items: list<string>` payload; `update()` therefore **appends** | drops the whole list key |
| Set | `SADD` a `members: list<string>` payload (additive) | drops the whole key |
| Sorted set | `ZADD` a `scoredMembers: array<string, float>` payload (adds new, rescores existing) | drops the whole key |

For finer operations — pop one list item, remove one set member,
change one score — wire a custom Action that calls `lpop`, `srem` or
`zrem` on `RedisClientInterface` directly.

`count()` is always `null` per ADR-002: these are cursor sources and
`KEYS *` is forbidden in production (O(n) blocking).

The string source additionally skips keys whose Redis `type` isn't
`string`, which prevents `WRONGTYPE` errors when a prefix overlaps
with keys of another type in the same namespace.

## What the hash data source does

- **`search()`** — Redis `SCAN` with cursor pagination. Returns at
  most `limit` records and exposes the next SCAN cursor as
  `DataPage::$nextCursor`. Filters are applied **client-side** (Redis
  hashes don't support server-side filtering without RediSearch).
- **`find($id)`** — `HGETALL` on `prefix+id`.
- **`count()`** — always `null` per ADR-002 (cursor sources).
  `KEYS *` is forbidden in production (O(n) blocking).
- **`create()`** — refuses if the key already exists.
- **`update()`** — `HMSET` semantics: payload fields **merge** into
  the existing hash (fields not in payload are preserved).
- **`delete()`** — `DEL` (idempotent).

## Filter operators

Filtering works differently for the hash source than for the other
four, because only hashes have per-record values to match against.

**Hash source** — filters client-side on the materialised page via
core's `InMemoryValueMatcher`, which handles the **whole**
`FilterOperator` enum: `Eq`, `Neq`, `In`, `Nin`, `Like`, `Gt`, `Gte`,
`Lt`, `Lte`, `Between`, `IsNull`, `IsNotNull`. Boolean comparisons
coerce string/int representations (`'1'`, `'true'`, `'on'`, `'yes'` →
`true`); `Like` is a case-insensitive substring test.

**String / list / set / sorted-set sources** — filter at the key
level through `ScanPatternResolver`, which turns the `id` criterion
into a Redis `SCAN MATCH` glob. Only `FilterOperator::Like` on `id`
translates; a needle with no glob metacharacters is auto-wrapped as
`*needle*` (contains semantics, mirroring SQL `LIKE`). Any other
operator, or a filter on any other property, degrades to
match-everything rather than erroring — Redis cannot SCAN on values.

For richer querying, ship your own RediSearch-backed data source —
the `WritableDataSourceInterface` contract is identical.

## Why client-side filtering?

Redis keys are O(1) lookups but O(n) scans. Server-side filtering
would require RediSearch (or a parallel index). For collections of
a few hundred records (the typical feature-flag scale), client-side
is fine and avoids the operational overhead of RediSearch. For
collections > 5k records, write a custom data source.

## See also

- [ADR-002 — DataPage::total semantics (cursor pagination)](../../adr/0002-data-page-total-semantics.md) — why count returns null.
- [`docs/user/cookbook/build-your-own-adapter.md`](../cookbook/build-your-own-adapter.md) — how to write a RediSearch-backed alternative.
