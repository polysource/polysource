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
that preselects the Twig template. This is core's own `TextField`,
verbatim:

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
final class FieldDto
{
    public function __construct(
        public readonly string $property,
        public readonly ?string $label = null,
        public readonly ?string $template = null,
        public readonly ?string $permission = null,
        public readonly bool $sortable = false,
        public readonly array $pages = ['index', 'detail', 'edit', 'new'],
        public readonly array $customOptions = [],
    ) {}

    public function isOnPage(string $page): bool;
}
```

The view layer reads `$pages` to decide whether the field renders on
the current screen, and `$customOptions` for theme-specific tweaks.

## The field types core ships

Since v0.7.1, `polysource/core` ships **five concrete field types**
under `Polysource\Core\Field\`. Use them directly — you do not need
to declare your own for the common cases:

| Class | Template it preselects | What it renders |
|---|---|---|
| `TextField` | `@Polysource/field/text.html.twig` | Plain text, escaped. |
| `IdField` | `@Polysource/field/id.html.twig` | An identifier, monospace with a copy-friendly affordance. |
| `DateTimeField` | `@Polysource/field/datetime.html.twig` | Locale-aware timestamp; accepts ISO 8601 strings, `DateTimeInterface`, or Unix timestamps. |
| `CodeField` | `@Polysource/field/code.html.twig` | Monospace block for JSON payloads, stack traces, log lines. |
| `BooleanField` | `@Polysource/field/boolean.html.twig` | True / false badge. |

All five are `final`, all use `FieldTrait`, so every builder method
above is available on them.

`polysource/twig-theme` ships one more template with no matching
class: `@Polysource/field/generic.html.twig`, the fallback used when
a field declares no template. Reference it explicitly with
`->setTemplate()` if you want `var_export`-style output.

### Writing your own field type

When none of the five fits — a status chip with your domain's colour
mapping, a currency renderer, a thumbnail — compose `FieldTrait` with
a one-line factory that preselects your template:

```php
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class MoneyField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('admin/field/_money.html.twig');
    }
}
```

That is the whole pattern — the shipped types are written exactly
this way.

## Wiring fields into a resource

Fields are produced by `ResourceInterface::configureFields()`:

```php
use Polysource\Core\Field\CodeField;
use Polysource\Core\Field\DateTimeField;
use Polysource\Core\Field\IdField;
use Polysource\Core\Field\TextField;

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

- [resource.md](./resource.md) — `configureFields()` is one of eight
  declarations a resource makes.
- [data-source.md](./data-source.md) — where the rendered values come
  from.
- [permission.md](./permission.md) — how `setPermission()` is checked.
- [../cookbook/messenger-failed-dashboard.md](../cookbook/messenger-failed-dashboard.md)
  — a complete `configureFields()` for the Messenger dashboard.
