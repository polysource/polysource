# API reference

This page is the index of Polysource's **public** API. Each entry
points at the canonical conceptual page (with rationale, examples,
caveats) and gives a one-line signature reminder for quick lookup.

If you need narrative, start with [../concepts/](../concepts/). If you
just need a signature in front of you while coding, this page is the
right starting point.

> **Status.** Current release: **v1.1.0 (2026-08-07)**. The public API
> has been **frozen since v1.0.0** under strict SemVer — no breaking
> change lands outside a major. Additions (like the v1.1 row-detail
> contracts below) arrive as new opt-in types, never as changes to
> existing signatures. Every entry below comes from source on the
> `main` branch; if a signature here disagrees with what your IDE
> shows, trust your IDE — open an issue so we can fix the doc.

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

### `Polysource\Core\Action\StyledActionInterface` *(extends `ActionInterface`)*

```php
public function getCssVariant(): string;   // 'primary', 'danger', … (no `btn-` prefix)
public function getConfirmation(): ?string;
```

Opt-in UI hints. Actions that don't implement it render with the
`secondary` variant and no confirmation prompt — the theme decides,
not the action name.
[concept page](../concepts/action.md)

### `Polysource\Core\Field\FieldInterface`

```php
public static function new(string $property, ?string $label = null): self;
public function getAsDto(): FieldDto;
```

Composed with `FieldTrait` to build concrete fields.
[concept page](../concepts/field.md)

### Concrete field types *(since v0.7.1)*

Core ships five ready-to-use field classes, all
`final class … implements FieldInterface` with `use FieldTrait`, all
constructed via `::new($property, ?$label)`:

| Class | Renders with | Use for |
|---|---|---|
| `Polysource\Core\Field\TextField` | `@Polysource/field/text.html.twig` | plain scalar values, HTML-escaped |
| `Polysource\Core\Field\IdField` | `@Polysource/field/id.html.twig` | identifiers, monospace + copy affordance |
| `Polysource\Core\Field\CodeField` | `@Polysource/field/code.html.twig` | JSON payloads, stack traces, log lines |
| `Polysource\Core\Field\BooleanField` | `@Polysource/field/boolean.html.twig` | true/false as a badge |
| `Polysource\Core\Field\DateTimeField` | `@Polysource/field/datetime.html.twig` | dates and timestamps |

Every `FieldTrait` builder method is available on them:
`setLabel()`, `setTemplate()`, `setPermission()`, `setSortable()`,
`onPages()`, `onlyOnIndex()`, `onlyOnDetail()`, `onlyOnForms()`,
`hideOnIndex()`, `setCustomOption()`. Write a custom field class only
when none of the five fits.
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

Note the asymmetry: `getSupportedOperators()` returns
`list<string>` (the backing values, e.g. `['eq', 'in', 'like']`)
while `FilterCriterion::$operator` is the `FilterOperator` enum
itself. The list is a declaration for the UI; the criterion is the
typed value that reaches `applyToQuery()`.

## Value objects

All immutable: `final class` with `readonly` promoted constructor
properties (cf. ADR-004). Use the `with*()` methods to derive a
modified instance.

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
    public FilterOperator $operator,
    public mixed $value = null,
);
```

`$operator` is the `FilterOperator` enum (see below), not a string —
it has been since v0.7.0. Build a criterion with
`new FilterCriterion('status', FilterOperator::In, ['failed'])`, or
parse an untrusted string with `FilterOperator::tryFrom($input)`.

### `Polysource\Core\Query\FilterOperator`

```php
enum FilterOperator: string
{
    case Eq = 'eq';           case Neq = 'neq';
    case Gt = 'gt';           case Gte = 'gte';
    case Lt = 'lt';           case Lte = 'lte';
    case Like = 'like';       case In = 'in';
    case Nin = 'nin';         case Between = 'between';
    case IsNull = 'null';     case IsNotNull = 'notnull';
}
```

The canonical operator vocabulary — **12 cases, closed**. Every
adapter translates these into its native query language. There is no
way to add an adapter-specific operator to this enum from outside
core: an adapter that needs extra selectivity expresses it through
the criterion's `$value` (or a dedicated `FilterInterface`
implementation whose `applyToQuery()` does the work), not through a
new operator. The backing strings are stable — they round-trip
through URLs (`?filter[name][op]=…`) and saved-view JSON.

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

### `Polysource\Core\RowDetail\RowDetail` *(since v1.1.0)*

```php
/** @param array<string, mixed> $context */
public static function template(string $template, array $context = []): self;

/** @param array<string, mixed> $parentFilters property => value equality scoping */
public static function listing(string $resourceName, array $parentFilters = [], ?int $pageSize = null): self;

public function isListing(): bool;

public readonly ?string $template;
public readonly array $context;
public readonly ?string $listingResource;
public readonly array $listingFilters;
public readonly ?int $listingPageSize;
```

Describes what to render inside an expanded row. Two shapes, one VO:
`::template()` renders a Twig template (the template always receives
the row's entity as `entity` on top of the given context);
`::listing()` embeds another registered Polysource resource as a
read-only nested table — the master/detail case — scoped to the
parent row by the `$parentFilters` equality map. The constructor is
private on purpose; use the two named constructors.
[row details](../row-details.md)

## Row-detail contracts *(since v1.1.0)*

### `Polysource\Bundle\RowDetail\HasRowDetailsInterface`

```php
public function hasRowDetail(DataRecord $record): bool;
public function getRowDetail(DataRecord $record): ?RowDetail;
public function getRowDetailPermission(): ?string;
```

Opt-in capability for the **native** Polysource listing: a resource
implements it alongside `ResourceInterface` and the bundle picks it
up by `instanceof` — the frozen v1.0 `ResourceInterface` contract is
untouched. `hasRowDetail()` is called once per visible row while
rendering the index, so keep it cheap; `getRowDetail()` is called
only by the lazy detail-panel endpoint, so heavier work belongs
there. Returning `null` from `getRowDetail()` 404s the panel.

### `Polysource\EasyAdminFilterBridge\RowDetail\RowDetailProviderInterface`

```php
/** @return class-string */
public function getSupportedEntity(): string;
public function getPermission(): ?string;
public function getRowDetail(object $entity): RowDetail;
```

The same capability for **EasyAdmin** CRUD listings, declared per
Doctrine entity rather than per resource. Implementations are
auto-registered through the `polysource.row_detail_provider` tag
(autoconfigured on this interface); one provider per entity class,
last registration wins, so a host can override a vendor-shipped
provider.

### `Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider`

```php
abstract protected function template(): string;
/** @return array<string, mixed> */
protected function context(object $entity): array;   // defaults to []
public function getPermission(): ?string;            // defaults to null
```

Convenience base for the 80% case. Subclass it, implement
`getSupportedEntity()` and `template()`, and override `context()` /
`getPermission()` only when you need more.

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
public const VERSION = '1.1.0';

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
| `Filter\FailedMessageFilter` | `FilterInterface` for the failed-message queue. Named constructors `::messageClass()` and `::exceptionClass()`. |
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
- The `Polysource\Bundle\View\PolysourceView` value object — the
  controller-to-listener bridge. It carries `$template`,
  `$variables`, `$statusCode` and (since v1.1) `$headers`, a
  `array<string, string>` map the render listener applies to both
  the Twig response and the JSON fallback. It remains **internal**:
  it was not promoted into the frozen v1.0 surface, so treat its
  shape as subject to change.

## See also

- [../concepts/](../concepts/) — narrative explanations.
- [../adapters/messenger.md](../adapters/messenger.md) — adapter
  reference.
- [Architecture Decision Records](../../adr/) — the *why* behind the
  signatures above.
