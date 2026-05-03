# Concept — Field

A **field** is a render hint: it tells Polysource how to display one
property of a `DataRecord` on a given page. Fields don't carry the
*value* of the property — the value comes from the `DataRecord` at
render time. Fields describe **how** to render it (label, template,
which pages, sortable or not, permission attribute).

## The interface

```php
namespace Polysource\Core\Field;

interface FieldInterface
{
    public static function new(string $property, ?string $label = null): self;
    public function getAsDto(): FieldDto;
}
```

That's the contract. A static factory plus a single method that
materialises an immutable snapshot (`FieldDto`) for the UI.

## The fluent trait

```php
trait FieldTrait
{
    public static function new(string $property, ?string $label = null): self;

    public function setLabel(?string $label): static;
    public function setTemplate(string $template): static;
    public function setPermission(?string $permission): static;
    public function setSortable(bool $sortable = true): static;
    public function onPages(array $pages): static;
    public function onlyOnIndex(): static;
    public function onlyOnDetail(): static;
    public function onlyOnForms(): static;
    public function hideOnIndex(): static;
    public function setCustomOption(string $name, mixed $value): static;

    public function getAsDto(): FieldDto;
}
```

Concrete field types use `FieldTrait` plus a 1-line `new()` factory
that preselects the Twig template:

```php
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class TextField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/text.html.twig');
    }
}
```

> **Named exception to immutability.** `FieldTrait` is a *mutable
> fluent builder by design* (setters return `static` and mutate
> `$this`). This matches the user-facing DX (`->setSortable()->onlyOnIndex()`)
> and is the only place in `polysource/core` that is intentionally
> mutable. **Never share a single field instance between two resources.**
> Always construct fresh instances through the static factory.

## The immutable DTO

`getAsDto()` materialises the builder state as an immutable snapshot
the UI consumes:

```php
final readonly class FieldDto
{
    public function __construct(
        public string $property,
        public ?string $label = null,
        public ?string $template = null,
        public ?string $permission = null,
        public bool $sortable = false,
        public array $pages = ['index', 'detail', 'edit', 'new'],
        public array $customOptions = [],
    ) {}

    public function isOnPage(string $page): bool;
}
```

The view layer reads `$pages` to decide whether the field renders on
the current screen, and `$customOptions` for theme-specific tweaks.

## What ships in v0.1

`polysource/core` v0.1 ships **only** the abstract `FieldInterface`
and `FieldTrait`. There are **no built-in concrete field types** yet
(see ADR-011 for the deferred decision). The Twig theme however
already ships **six** templates ready to be referenced from your own
field classes:

| Template | What it renders |
|---|---|
| `@Polysource/field/text.html.twig` | Plain text, escaped. |
| `@Polysource/field/id.html.twig` | An identifier styled as a chip. |
| `@Polysource/field/datetime.html.twig` | Formatted timestamp. |
| `@Polysource/field/code.html.twig` | A `<pre>` block for JSON / payloads. |
| `@Polysource/field/boolean.html.twig` | True / false badge. |
| `@Polysource/field/generic.html.twig` | Fallback — calls `var_export` on the value. |

You declare a field type in your host app by composing the trait with
the appropriate template:

```php
final class IdField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/id.html.twig');
    }
}
```

The same pattern produces `TextField`, `DateTimeField`, `CodeField`,
`BooleanField` — five short classes total. The full set lives under
`examples/messenger-demo/src/Field/` in this repository if you want a
concrete reference.

A future Polysource release will ship those five concrete types in
`polysource/core` so you don't need to re-declare them; until then,
copy them into your application.

## Wiring fields into a resource

Fields are produced by `ResourceInterface::configureFields()`:

```php
public function configureFields(string $page): iterable
{
    yield IdField::new('message_class', 'Message');
    yield TextField::new('exception_class', 'Exception')->onlyOnIndex();
    yield TextField::new('exception_message', 'Reason');
    yield DateTimeField::new('failed_at', 'Failed at');
    yield CodeField::new('payload', 'Payload')->onlyOnDetail();
}
```

`$page` is one of `'index'`, `'detail'`, `'edit'`, `'new'`. Branch on
it for structurally-different layouts; for simple cases the shorthand
helpers (`onlyOnIndex`, `onlyOnDetail`, `onlyOnForms`, `hideOnIndex`)
are enough.

## Sorting

```php
yield TextField::new('priority')->setSortable();
```

Sortable fields render as clickable column headers; clicking emits
the appropriate `withSort()` call into `DataQuery`. The data source
must of course be able to satisfy the sort — read-only sources that
ignore unknown sort columns are acceptable, but throwing
`UnsupportedOperationException` is more honest.

## Per-field permissions

```php
yield CodeField::new('payload')
    ->onlyOnDetail()
    ->setPermission('POLYSOURCE_FAILED_MESSAGE_VIEW_PAYLOAD');
```

The view layer skips fields whose `setPermission()` attribute is not
granted to the current user. Useful for redacting sensitive columns
(PII, secrets) without writing two resources.

## See also

- [resource.md](./resource.md) — `configureFields()` is one of seven
  declarations a resource makes.
- [data-source.md](./data-source.md) — where the rendered values come
  from.
- [permission.md](./permission.md) — how `setPermission()` is checked.
- [../cookbook/messenger-failed-dashboard.md](../cookbook/messenger-failed-dashboard.md)
  — a complete `configureFields()` for the Messenger dashboard.
