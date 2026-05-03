# Concept — Data source

A **data source** is the read (and optionally write) abstraction
behind every Polysource resource. It hides whether records come from a
SQL database, a Messenger transport, an S3 bucket, a Redis hash, an
HTTP API or a YAML file. The rest of Polysource only ever sees the
`DataSourceInterface` contract.

## The 3-method read contract

```php
namespace Polysource\Core\DataSource;

interface DataSourceInterface
{
    public function search(DataQuery $query): DataPage;
    public function find(string|int $identifier): ?DataRecord;
    public function count(DataQuery $query): ?int;
}
```

That's the **entire** read surface. Three methods. The intent is
explicit: any backend that can answer those three questions can be
exposed in the admin.

### `search($query): DataPage`

Returns a paged result set for the given query. The query carries
filters, sort, search text, and pagination — see [DataQuery](#dataquery)
below. The page contains an iterable of `DataRecord` plus optional
`total`, `nextCursor`, `prevCursor`.

### `find($identifier): ?DataRecord`

Look up a single record by its id. Returns `null` when the record
doesn't exist; **never** throws on a missing record. The id type is
`string|int` to accommodate numeric primary keys (Doctrine), opaque
UUIDs, S3 object keys, Messenger envelope ids, etc.

### `count($query): ?int`

Total number of records matching `$query`. Returning **`null`** is
legitimate and means "I don't know" — the UI then uses cursor
pagination instead of `Page X / Y`.

| Source kind | What `count()` returns |
|---|---|
| Doctrine ORM, MySQL `COUNT(*)` | `int` |
| Messenger failed transport (Doctrine, Redis, AMQP) | `null` — no cheap total |
| S3 `ListObjectsV2` | `null` — total only known after full scan |
| Meilisearch search | `int` (the API includes it) |

This is not laziness — it is honesty. A fake count would mislead the
operator. ADR-002 in the repository spells out the rationale.

## The optional write contract

```php
interface WritableDataSourceInterface extends DataSourceInterface
{
    public function create(DataPayload $payload): DataRecord;
    public function update(string|int $identifier, DataPayload $payload): DataRecord;
    public function delete(string|int $identifier): void;
}
```

Read-only sources don't implement this interface — that's the Interface
Segregation Principle in action. The UI auto-detects whether a source
is writable and toggles Create / Edit / Delete buttons accordingly. A
read-only Messenger dashboard never shows "Create".

## The optional batch contract

```php
interface BatchableDataSourceInterface extends DataSourceInterface
{
    /** @return array<string|int, DataRecord> */
    public function findMany(array $identifiers): array;
}
```

Implement only if the underlying store has a real batched primitive
(SQL `IN (...)`, Redis `MGET`, an HTTP endpoint that takes a list).
Adapters where `findMany([1, 2, 3])` would just be three `find()` calls
in a loop **must not** implement this — the framework handles that
fallback itself, and the marker interface is the signal that batched
lookups are actually cheaper.

## Value objects passed across the boundary

### `DataQuery`

```php
final readonly class DataQuery
{
    public function __construct(
        public string $resourceName,
        public ?string $searchText = null,
        public array $filters = [],     // map<string, FilterCriterion>
        public array $sort = [],        // map<string, SortDirection>
        public ?Pagination $pagination = null,
    ) {}

    public function withSearchText(?string $searchText): self;
    public function withFilter(string $name, FilterCriterion $criterion): self;
    public function withoutFilter(string $name): self;
    public function withSort(string $property, SortDirection $direction): self;
    public function withPagination(?Pagination $pagination): self;
}
```

Immutable. Each `with*()` method returns a new instance — adapters
**never** mutate the query they receive.

### `DataPage`

```php
final readonly class DataPage
{
    public function __construct(
        public iterable $items,         // iterable<DataRecord>
        public ?int $total = null,
        public ?string $nextCursor = null,
        public ?string $prevCursor = null,
    ) {}
}
```

`$total === null` is the cursor-pagination signal.

### `DataRecord`

```php
final readonly class DataRecord
{
    public function __construct(
        public string|int $identifier,
        public array $properties,       // map<string, mixed>
        public mixed $rawSource = null,
    ) {}
}
```

`$properties` is a flat `array<string, mixed>` keyed by field property
name. `$rawSource` is an **opaque** escape hatch for adapters: the
Messenger adapter stores the original `Symfony\Component\Messenger\Envelope`
there so the Retry action can re-dispatch it. Avoid relying on
`$rawSource` from generic code — it makes the call site
adapter-specific.

### `DataPayload`

```php
final readonly class DataPayload
{
    public function __construct(public array $properties) {}

    public function get(string $property, mixed $default = null): mixed;
    public function has(string $property): bool;
    public function with(string $property, mixed $value): self;
    public function without(string $property): self;
}
```

The input to `create()` and `update()`. Adapters decide how to map
keys to their native model (Doctrine setter call, Redis `HMSET`, HTTP
`POST` body, …).

### `Pagination`

```php
final readonly class Pagination
{
    public function __construct(
        public int $offset = 0,
        public int $limit = 20,
        public ?string $cursor = null,
    ) {}
}
```

Supports both offset/limit and cursor styles. The constructor rejects
`offset < 0` and `limit < 1`. The bundle additionally caps `limit` at
`polysource.max_page_size` (default 200).

### `FilterCriterion`

```php
final readonly class FilterCriterion
{
    public function __construct(
        public string $property,
        public string $operator,
        public mixed $value = null,
    ) {}
}
```

Standard operators (an adapter may declare additional ones):

| Operator | Meaning |
|---|---|
| `eq`, `neq` | equality / inequality |
| `gt`, `gte`, `lt`, `lte` | numeric / date comparisons |
| `like` | substring (semantics adapter-dependent) |
| `in`, `nin` | membership in a list |
| `between` | range — value must be a 2-element array |
| `null`, `notnull` | presence check — value ignored |

Adapters translate operators into their native query language
(Doctrine DQL, Redis `SCAN MATCH`, HTTP query string, Meilisearch
filter syntax). An adapter that doesn't support a given operator
should throw `UnsupportedOperationException` rather than silently
ignoring it.

### `SortDirection`

```php
enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}
```

## Reporting unsupported operations

```php
namespace Polysource\Core\Exception;

final class UnsupportedOperationException extends DataSourceException
{
    public static function forMethod(string $method, string $reason = ''): self;
}
```

Adapters that can't perform a specific operation **must throw**
`UnsupportedOperationException` rather than returning a fake value or
silently no-op'ing. The UI catches it and adapts (hides the count,
falls back to cursor pagination, disables a button, …).

Examples already in the codebase:

- `MessengerFailedDataSource::count()` returns `null` directly
  (count itself is legitimate, just unknown).
- An imaginary read-only S3 source asked to `update()` would throw
  `UnsupportedOperationException::forMethod('update', 'S3 read-only adapter — use the writable variant.')`.

## How a data source is registered

Data sources are services tagged with `polysource.data_source`. The
recommended way is via the `#[AsResource]` attribute: the bundle's
extension automatically wires the resource's data source argument from
the constructor. For manual control, declare the service explicitly in
`services.yaml`:

```yaml
services:
    App\Polysource\FeatureFlagDataSource:
        arguments:
            $redis: '@Redis'
        tags: ['polysource.data_source']
```

## Designing your own data source

A correct data source:

1. **Never leaks storage-specific types in its public signature.** No
   `QueryBuilder`, no `Envelope`, no `\Redis`, no `Aws\S3\S3Client` —
   only `DataQuery`, `DataPage`, `DataRecord`, `DataPayload`.
2. **Returns immutable values.** Every `DataPage` and every
   `DataRecord` is `final readonly` already; just don't mutate the
   inputs.
3. **Honestly reports `count()`.** Return `null` rather than a guess.
4. **Throws `UnsupportedOperationException`** for unsupported
   operators or operations.
5. **Logs but doesn't crash on per-record mapping failures.** A single
   malformed record must not bring down the whole index page — see
   `MessengerFailedDataSource::search()` for the canonical pattern
   (warn-and-skip).

## See also

- [resource.md](./resource.md) — what binds a data source to a screen.
- [field.md](./field.md) — how `DataRecord` properties become columns.
- [../adapters/messenger.md](../adapters/messenger.md) — a fully worked
  read-only data source backed by Messenger's failed transport.
