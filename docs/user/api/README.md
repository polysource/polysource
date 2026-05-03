# API reference

This page is the index of Polysource's **public** API. Each entry
points at the canonical conceptual page (with rationale, examples,
caveats) and gives a one-line signature reminder for quick lookup.

If you need narrative, start with [../concepts/](../concepts/). If you
just need a signature in front of you while coding, this page is the
right starting point.

> **Status.** Polysource is pre-v0.1.0. Public API is **not yet
> frozen**. Every entry below comes from source on the `main` branch;
> if a signature here disagrees with what your IDE shows, trust your
> IDE — open an issue so we can fix the doc.

## The three core interfaces

Acceptance criterion of Phase 9 (development plan): *"the API reference
lists the three main interfaces."* Here they are.

### `Polysource\Core\DataSource\DataSourceInterface`

```php
public function search(DataQuery $query): DataPage;
public function find(string|int $identifier): ?DataRecord;
public function count(DataQuery $query): ?int;
```

The 3-method read contract every adapter implements. `count()` may
return `null` to mean "unknown".
[concept page](../concepts/data-source.md)

### `Polysource\Core\Resource\ResourceInterface`

```php
public function getName(): string;
public function getLabel(): string;
public function getIdentifierProperty(): string;
public function getDataSource(): DataSourceInterface;
public function configureFields(string $page): iterable;     // iterable<FieldInterface>
public function configureActions(): iterable;                // iterable<ActionInterface>
public function configureFilters(): iterable;                // iterable<FilterInterface>
public function getPermission(): ?string;
```

What every admin screen declares. Use `AbstractResource` as a base.
[concept page](../concepts/resource.md)

### `Polysource\Core\Permission\PermissionInterface`

```php
public function isGranted(string $attribute, mixed $subject = null): bool;
```

Single-method bridge to whichever auth system your app uses (Symfony
Security by default).
[concept page](../concepts/permission.md)

## Optional sub-contracts

### `Polysource\Core\DataSource\WritableDataSourceInterface` *(extends `DataSourceInterface`)*

```php
public function create(DataPayload $payload): DataRecord;
public function update(string|int $identifier, DataPayload $payload): DataRecord;
public function delete(string|int $identifier): void;
```

Implement to enable Create / Edit / Delete buttons.
[concept page](../concepts/data-source.md#the-optional-write-contract)

### `Polysource\Core\DataSource\BatchableDataSourceInterface` *(extends `DataSourceInterface`)*

```php
/** @return array<string|int, DataRecord> keyed by identifier */
public function findMany(array $identifiers): array;
```

Marker interface — only implement when the underlying store has a real
batched primitive.
[concept page](../concepts/data-source.md#the-optional-batch-contract)

### `Polysource\Core\Action\ActionInterface`

```php
public function getName(): string;
public function getLabel(): string;
public function getIcon(): ?string;
public function getPermission(): ?string;
public function isDisplayed(array $context = []): bool;
```

Base for every action.
[concept page](../concepts/action.md#the-base-contract)

### `Polysource\Core\Action\InlineActionInterface` *(extends `ActionInterface`)*

```php
public function execute(DataRecord $record): ActionResult;
```

[concept page](../concepts/action.md#inline-actions)

### `Polysource\Core\Action\BulkActionInterface` *(extends `ActionInterface`)*

```php
/** @param iterable<DataRecord> $records */
public function executeBatch(iterable $records): ActionResult;
```

[concept page](../concepts/action.md#bulk-actions)

### `Polysource\Core\Field\FieldInterface`

```php
public static function new(string $property, ?string $label = null): self;
public function getAsDto(): FieldDto;
```

Composed with `FieldTrait` to build concrete fields.
[concept page](../concepts/field.md)

### `Polysource\Core\Filter\FilterInterface`

```php
public function getProperty(): string;
public function getLabel(): string;
/** @return list<string> */
public function getSupportedOperators(): array;
public function applyToQuery(DataQuery $query, FilterCriterion $criterion): DataQuery;
public function getAsDto(): FilterDto;
```

Filters declare which property + operator combinations they expose
and how to translate a `FilterCriterion` into the resource's
`DataQuery`. Filters never mutate — they always return a fresh
`DataQuery`.

## Value objects

All immutable `final readonly class` (cf. ADR-004). Use the `with*()`
methods to derive a modified instance.

### `Polysource\Core\Query\DataQuery`

```php
public function __construct(
    public string $resourceName,
    public ?string $searchText = null,
    /** @var array<string, FilterCriterion> */
    public array $filters = [],
    /** @var array<string, SortDirection> */
    public array $sort = [],
    public ?Pagination $pagination = null,
);

public function withSearchText(?string $searchText): self;
public function withFilter(string $name, FilterCriterion $criterion): self;
public function withoutFilter(string $name): self;
public function withSort(string $property, SortDirection $direction): self;
public function withPagination(?Pagination $pagination): self;
```

### `Polysource\Core\Query\DataPage`

```php
public function __construct(
    /** @var iterable<DataRecord> */
    public iterable $items,
    public ?int $total = null,
    public ?string $nextCursor = null,
    public ?string $prevCursor = null,
);

/** @return list<DataRecord> */
public function asArray(): array;
public function isEmpty(): bool;
```

### `Polysource\Core\Query\DataRecord`

```php
public function __construct(
    public string|int $identifier,
    /** @var array<string, mixed> */
    public array $properties,
    public mixed $rawSource = null,
);

public function get(string $property, mixed $default = null): mixed;
public function has(string $property): bool;
```

### `Polysource\Core\Query\DataPayload`

```php
public function __construct(
    /** @var array<string, mixed> */
    public array $properties,
);

public function get(string $property, mixed $default = null): mixed;
public function has(string $property): bool;
public function with(string $property, mixed $value): self;
public function without(string $property): self;
```

### `Polysource\Core\Query\Pagination`

```php
public function __construct(
    public int $offset = 0,    // throws InvalidArgumentException if < 0
    public int $limit = 20,    // throws InvalidArgumentException if < 1
    public ?string $cursor = null,
);

public function withOffset(int $offset): self;
public function withLimit(int $limit): self;
public function withCursor(?string $cursor): self;
```

### `Polysource\Core\Query\FilterCriterion`

```php
public function __construct(
    public string $property,
    public string $operator,
    public mixed $value = null,
);
```

Standard operators: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`,
`in`, `nin`, `between`, `null`, `notnull`. Adapter-specific operators
allowed.

### `Polysource\Core\Query\SortDirection`

```php
enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}
```

### `Polysource\Core\Action\ActionResult`

```php
public function __construct(
    public bool $success,
    public ?string $message = null,
    /** @var array<string, mixed> */
    public array $context = [],
);

public static function success(?string $message = null, array $context = []): self;
public static function failure(string $message, array $context = []): self;
```

### `Polysource\Core\Field\FieldDto`

```php
public function __construct(
    public string $property,
    public ?string $label = null,
    public ?string $template = null,
    public ?string $permission = null,
    public bool $sortable = false,
    /** @var list<string> */
    public array $pages = ['index', 'detail', 'edit', 'new'],
    /** @var array<string, mixed> */
    public array $customOptions = [],
);

public function isOnPage(string $page): bool;
```

## Base classes and traits

### `Polysource\Core\Resource\AbstractResource`

```php
public function __construct(
    protected readonly DataSourceInterface $dataSource,
);

// defaults: getIdentifierProperty(): 'id'
//           configureFields/Actions/Filters() => []
//           getPermission(): null
```

[concept page](../concepts/resource.md#use-the-abstract-base-class)

### `Polysource\Core\Field\FieldTrait`

Fluent builder used by every concrete field type.
[concept page](../concepts/field.md#the-fluent-trait)

> **Named exception to immutability.** `FieldTrait` is intentionally
> mutable. Setters return `static` and mutate `$this`. **Never** share
> a single field instance between two resources — always construct
> fresh ones via the static factory.

## Exceptions

```
Polysource\Core\Exception\DataSourceException
└── Polysource\Core\Exception\UnsupportedOperationException
└── Polysource\Core\Exception\ResourceNotFoundException
```

### `UnsupportedOperationException`

```php
public static function forMethod(string $method, string $reason = ''): self;
```

Adapters that can't perform a specific operation **must throw**
this rather than returning a fake value.
[concept page](../concepts/data-source.md#reporting-unsupported-operations)

## Constants

### `Polysource\Core\Polysource`

```php
public const VERSION = '0.1.0-dev';

public const PAGE_INDEX = 'index';
public const PAGE_DETAIL = 'detail';
public const PAGE_EDIT = 'edit';
public const PAGE_NEW = 'new';

public const TAG_DATA_SOURCE = 'polysource.data_source';
public const TAG_RESOURCE    = 'polysource.resource';
```

## Symfony bundle public surface

All under `Polysource\Bundle\`.

| Class | Purpose |
|---|---|
| `PolysourceBundle` | Auto-registered via the `symfony-bundle` composer type. |
| `Attribute\AsResource` | Apply to a `ResourceInterface` class to auto-tag with `polysource.resource`. |
| `Context\AdminContext` | Per-request admin state (`request`, `resource`, `action`, `recordId`, `locale`, `user`, `query`). Inject into your controllers / listeners. |
| `Context\AdminContextProvider` | `kernel.reset`-tagged provider that holds the current `AdminContext`. |
| `Routing\PolysourceUrlGenerator` | Helper that wraps `UrlGeneratorInterface` to build resource URLs by slug + action name. |
| `Security\SymfonyAuthorizationCheckerPermission` | Default `PermissionInterface` implementation backed by Symfony Security; fail-closed on missing firewall. |

## Messenger adapter public surface

All under `Polysource\Adapter\Messenger\`.

| Class | Purpose |
|---|---|
| `Resource\FailedMessageResource` | The dashboard resource. **Not `final`** — subclass to add `configureFields()`. |
| `DataSource\MessengerFailedDataSource` | Read-only data source over a `ListableReceiverInterface`. |
| `DataSource\EnvelopeMapper` | Converts an `Envelope` to a `DataRecord`. |
| `Action\RetryFailedMessageAction` | Inline. |
| `Action\DismissFailedMessageAction` | Inline. |
| `Action\RetryAllFailedMessagesAction` | Bulk. |
| `Action\PurgeFailedMessagesAction` | Bulk. |

Reference: [../adapters/messenger.md](../adapters/messenger.md).

## What is *not* part of the public API

- Any class under `Polysource\Bundle\Controller\`,
  `Polysource\Bundle\EventListener\`,
  `Polysource\Bundle\Registry\`. These are internal wiring and may
  change without notice.
- Any class with a "Tests" namespace.
- The `protected` properties of `AbstractResource` (`$dataSource`).
  Treat as read-only via the public getter.
- The `Polysource\Bundle\View\PolysourceView` value object — used as
  the controller-to-listener bridge, currently internal but may be
  promoted in v0.2.

## See also

- [../concepts/](../concepts/) — narrative explanations.
- [../adapters/messenger.md](../adapters/messenger.md) — adapter
  reference.
- [Architecture Decision Records](../../adr/) — the *why* behind the
  signatures above.
