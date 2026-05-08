# Cookbook — Build your own adapter

This walkthrough generalises the patterns from the 6 adapters
shipped in `polysource/*` (`adapter-doctrine`, `adapter-redis`,
`adapter-flysystem`, `adapter-http`, `adapter-meilisearch`,
`adapter-messenger`) into a recipe you can apply to any data
source: SQS, DynamoDB, GraphQL, gRPC, RediSearch, Algolia, your
internal RPC framework, anything.

The good news: the public contract is **3 methods read-only or 6
methods read+write**. If you can map "list with filters", "fetch
one by id", and (optionally) "create / update / delete", you can
ship a Polysource adapter in an afternoon.

## 1. Decide read-only vs writable

Two interfaces, in order of growing surface:

```php
namespace Polysource\Core\DataSource;

// 3 methods — required for every adapter
interface DataSourceInterface
{
    public function search(DataQuery $query): DataPage;
    public function find(string|int $identifier): ?DataRecord;
    public function count(DataQuery $query): ?int;
}

// 3 more methods — opt-in if your source supports writes
interface WritableDataSourceInterface extends DataSourceInterface
{
    public function create(DataPayload $payload): DataRecord;
    public function update(string|int $identifier, DataPayload $payload): DataRecord;
    public function delete(string|int $identifier): void;
}
```

**Rule of thumb:**
- Pure cache, immutable log, derived index → read-only
  (`adapter-messenger` is read-only; you can dismiss/retry
  envelopes via Action callbacks but the listing itself is read).
- Anything users need to mutate from the admin → writable.

Implementing only `DataSourceInterface` is fine — the UI
auto-detects the capability and hides the Create/Edit/Delete
buttons. Don't fake writes.

## 2. Pick a pagination strategy

Three real-world options. Pick by what your source supports
*cheaply*:

| Strategy | When | Returns |
|---|---|---|
| **Offset + exact total** | SQL, Doctrine, anything with cheap COUNT. | `DataPage($items, total: int, nextCursor: null)` |
| **Cursor + estimated total** | SCAN (Redis), Meilisearch, Elasticsearch. | `DataPage($items, total: ?int, nextCursor: 'opaque')` |
| **Cursor + no total** | S3 list, GitHub API v4, Stripe. | `DataPage($items, total: null, nextCursor: 'opaque')` |

The UI branches on `$total !== null`:
- Non-null → "Page X / Y" classic paginator.
- Null → "Next / Prev" cursor paginator.

Per ADR-002, `null` is the honest answer for un-countable sources.
Don't synthesise a fake total — `KEYS *` on Redis will take down
your production cluster.

## 3. Map filters to your query language

The data source receives `DataQuery::$filters` — a list of
`FilterCriterion(property, operator, value)`. Translate each one
into your source's native query language.

The 4 reference adapters give you a pattern by source type:

| Source | Translation pattern |
|---|---|
| Doctrine | `match($operator)` → DQL clauses (`andWhere(...)`, `setParameter(...)`) |
| Meilisearch | `match($operator)` → filter expression strings (`field = 'x'`, `field IN [a, b]`) |
| HTTP REST | Query params (`?role=admin`) — `eq` only by default; richer mapping via custom strategy |
| Redis hash | Client-side filter on the materialised page |
| Flysystem | Client-side filter on `extension`/`mimeType` properties |

**Whitelist properties.** Two patterns work:

1. **Explicit whitelist** at construction time:

   ```php
   new MyDataSource(
       client: ...,
       allowedFilters: [
           'name' => 'name',          // public property → native field
           'createdAt' => 'created_at',
       ],
   );
   ```

   Unknown filter properties are silently skipped. This is the
   pattern in `adapter-doctrine` — a typo in a filter form must
   never become an SQL clause on a sensitive column.

2. **Sanitise** the property name (alphanumeric + dot/underscore).
   `adapter-meilisearch` does this — Meilisearch enforces filter
   permissions server-side via `filterableAttributes`, so we just
   strip injection vectors at the client.

Either is fine; pick whichever matches your source's security model.

## 4. Build a `DataRecord`

Every record exposed to the UI is a `DataRecord(identifier,
properties, rawSource?)`:

- `identifier` — `string|int`. The primary key (Redis key suffix,
  Doctrine entity id, S3 object key, …).
- `properties` — `array<string, mixed>`. The columns the UI
  renders. Keep keys stable across pages.
- `rawSource` — optional, the underlying object (Doctrine entity,
  Flysystem `StorageAttributes`, raw HTTP response). Useful for
  hosts who want to escape into source-native APIs.

Convention from the shipped adapters:
- Datetime values → ISO-8601 strings (`format(\DATE_ATOM)`) so the
  Twig theme can render them uniformly.
- Booleans, scalars → kept as-is.
- Embedded objects → `json_encode` if the UI needs to display them.
- Sensitive fields (passwords, tokens, API keys) → **never**
  expose. The data source decides; resources can't undo this.

## 5. Optional: implement `BatchableDataSourceInterface`

If your source can fetch N records in one call (Doctrine
`findBy(['id' => [...]])`, Meilisearch
`getDocuments(['filter' => 'id IN [...]'])`), implement
`BatchableDataSourceInterface::findMany()` so the UI can avoid N+1
when rendering association columns.

If your `findMany()` would just be a `find()` loop, **don't**
implement it — the default fallback is the same thing minus the
extra interface surface.

## 6. Wire the resource

Resources are independent of data sources — you write one resource
per logical view, and each ships its own data source instance:

```php
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Resource\AbstractResource;

#[AsResource]
final class MyThingResource extends AbstractResource
{
    public function __construct(MyClient $client)
    {
        parent::__construct(new MyDataSource($client, /* config */));
    }

    public function getName(): string { return 'my-things'; }
    public function getLabel(): string { return 'My things'; }
    public function getPermission(): ?string { return 'POLYSOURCE_MY_THING_VIEW'; }

    public function configureFields(string $page): iterable { return []; }
    public function configureActions(): iterable { return []; }
    public function configureFilters(): iterable { return []; }
}
```

Or, for adapters that ship a base class (Doctrine / Redis /
Flysystem / HTTP / Meilisearch all do), subclass the base —
saves boilerplate.

## 7. Test with an in-memory fake

The best of the shipped adapters all ship an in-memory test fake
(`InMemoryRedisHashClient`, `InMemoryMeilisearchIndex`,
`InMemoryBulkJobStorage`) that implements just enough of the
backing store's behaviour to exercise the data source without
spinning a real server. Two reasons:

1. **CI speed.** Tests run in ~10ms each, no external services.
2. **Edge case coverage.** You can simulate transient failures,
   pagination boundaries, filter typos, empty results, deleted-
   mid-iteration, etc., far more easily than against a real
   server.

Pattern: ship the fake under `tests/InMemory/`, namespace
`Polysource\\Adapter\\YourThing\\Tests\\InMemory\\`. Hosts who
write their own custom data sources can re-use it as a drop-in
mock for their tests.

## 8. Bundle + DI registration

Every shipped adapter follows the same shape:

```
packages/adapter-yourthing/
├── composer.json
├── src/
│   ├── Client/                           # optional — wraps the underlying lib
│   ├── DataSource/YourThingDataSource.php
│   ├── Resource/YourThingResource.php    # convenience base, non-final
│   ├── DependencyInjection/PolysourceAdapterYourThingExtension.php
│   └── PolysourceAdapterYourThingBundle.php
├── Resources/
│   └── config/
│       └── services.php                  # autowire defaults; rarely opinionated
└── tests/
    ├── InMemory/                         # test fakes
    └── Unit/DataSource/...
```

The bundle implements `AdminPluginInterface` (`#[AsPlugin]`) per
ADR-018 so it surfaces in `polysource:plugins:list`. The extension
loads `services.php` which typically just enables autowire +
autoconfigure — **don't** auto-register the resource itself
(host decides which entities/indexes/buckets to admin).

## 9. Document it

Drop a `docs/user/adapters/yourthing.md` mirroring the structure
of the shipped ones:

- Install (composer require + bundle registration)
- Wire a resource (5-line code snippet)
- What the data source does (per-method behaviour)
- Filter operators supported
- When to use vs custom
- See-also links to ADRs

## 10. Common gotchas — learned the hard way

**Don't leak source-specific types in the public signature.** A
data source returns `DataRecord`, not `Doctrine\Entity\X` or
`StorageAttributes`. Keeping the leak inside `rawSource` (the
escape hatch) is fine; pinning it on the constructor isn't.

**Idempotent `delete()`.** All shipped adapters treat
"already-deleted" as success. The UI can double-click without
seeing an error, and audit logs stay clean.

**Don't fake the total count.** If your source can't count cheaply,
return `null` from `count()` — the UI handles cursor pagination
for you. Synthesising a count by enumerating is the opposite of
what cursor sources are for.

**Wrap third-party clients in a tiny interface.** `Predis\ClientInterface`
has 100+ methods; we expose 5 (`RedisHashClientInterface`).
`meilisearch-php`'s `Indexes` exposes 30+; we expose 4
(`MeilisearchIndexInterface`). This keeps your test fakes small
and lets hosts swap clients without rewriting your data source.

**Filter-property security.** Either whitelist explicitly
(Doctrine pattern) or sanitise to alnum+dot+underscore
(Meilisearch pattern). Never let a filter form's property name
flow unchecked into your query language.

**Test the cancellation/deletion-during-iteration edge cases.**
Real-world admins regularly delete items mid-pagination. Your
adapter should not crash with "key not found" — return whatever
you can and let the next page recover.

## See also

- [`adapters/doctrine.md`](../adapters/doctrine.md) — read+write over Doctrine ORM, whitelist filters, Doctrine-coupled.
- [`adapters/redis.md`](../adapters/redis.md) — read+write over Redis hashes, custom client interface, in-memory test fake.
- [`adapters/flysystem.md`](../adapters/flysystem.md) — read+write over Flysystem (S3/local/Azure/GCS), pre-existing-file guard.
- [`adapters/http.md`](../adapters/http.md) — read+write over REST APIs, pluggable pagination strategies.
- [`adapters/meilisearch.md`](../adapters/meilisearch.md) — search-first read+write, server-side filter expressions.
- [`adapters/messenger.md`](../adapters/messenger.md) — read-only over Symfony Messenger failed transports.
- [ADR-001 — DataRecord identifier type](../../adr/0001-data-record-identifier.md)
- [ADR-002 — DataPage::total semantics (cursor pagination)](../../adr/0002-data-page-total-semantics.md)
