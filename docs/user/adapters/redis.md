# `polysource/adapter-redis`

Read+write a namespaced collection of Redis hashes through the
Polysource admin. Canonical use cases: feature flags, lightweight
key-value config, session metadata, A/B test buckets.

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

The package autowires `PredisRedisHashClient` over any
`Predis\ClientInterface` already present in DI (typically wired by
`SncRedisBundle` or a manual service definition). Hosts on
`ext-redis` (no Predis) ship a 30-LOC adapter implementing
`RedisHashClientInterface`.

## Wire a resource

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
several resources with different prefixes (one per namespace).

## What the data source does

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

Client-side, supports: `eq`, `neq`, `in`, `like`. Boolean
comparisons coerce string/int representations (`'1'`, `'true'`,
`'on'`, `'yes'` → `true`). For richer querying, ship your own
RediSearch-backed data source — the `WritableDataSourceInterface`
contract is identical.

## Why client-side filtering?

Redis hashes are O(1) lookups but O(n) scans. Server-side filtering
would require RediSearch (or a parallel index). For collections of
a few hundred records (the typical feature-flag scale), client-side
is fine and avoids the operational overhead of RediSearch. For
collections > 5k records, write a custom data source.

## See also

- [ADR-002 — Pagination cursor](../../adr/0002-pagination-cursor.md) — why count returns null.
- [`docs/user/cookbook/build-your-own-adapter.md`](../cookbook/build-your-own-adapter.md) — how to write a RediSearch-backed alternative.
