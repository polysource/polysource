# Row conditional styles

> Since `polysource/easyadmin-filter-bridge` v0.3.0.

A Twig helper that emits a CSS class on table rows based on the
value of one of the entity's properties. Useful to highlight rows
by status, state, or any single-property condition.

## Usage

In your `templates/bundles/EasyAdminBundle/crud/index.html.twig`
(EA's standard host-side override path), override the
`table_body` block (or the inner row rendering — depending on
your EA version) and add the class via the helper.

> **Extend the bridge's template, not `@!EasyAdmin` directly.**
> `@!EasyAdmin/crud/index.html.twig` bypasses the bridge's spliced
> index template, silently removing the chips bar and the
> column-visibility / saved-views dropdowns from every index page.
> See [theming.md](./theming.md#the-safe-way-to-extend-the-index-page--read-this-first).

```twig
{% extends '@PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}

{% block table_body %}
    {% for entity in entities %}
        <tr class="{{ polysource_row_class(entity.instance, 'status', {
            refunded: 'table-danger',
            archived: 'text-muted',
            paid: 'table-success',
        }) }} {{ parent() | filter(...) }}">
            …
        </tr>
    {% endfor %}
{% endblock %}
```

The full signature:

```twig
polysource_row_class(entity, property, classMap, default = '')
```

- `entity` — the row's entity object (typically `entity.instance` in
  EA's index iteration).
- `property` — the property name. Resolved via `getProperty()` first,
  then `isProperty()`, then direct public property access.
- `classMap` — value → CSS class map. Keys can be strings, booleans,
  or enum values; the helper coerces consistently.
- `default` — class returned when the value isn't in the map, default `''`.

## What the helper handles

- Plain scalar properties (`getStatus(): string`)
- Boolean issers (`isActive(): bool`) — map keys `true`/`false` (string)
- `BackedEnum` values — map keys are the enum's `value`
- `UnitEnum` values — map keys are the enum's `name`
- Direct public properties (`$entity->status` without a getter)
- Returns the default for missing properties (no exception)

## What it doesn't handle

For more complex rules (combinations of properties, arithmetic on
amounts, time-based conditions), write your own Twig function in a
host-side extension. The bridge intentionally keeps this helper
narrow — single property → class map — to cover the 80% case
without becoming a mini-rules engine.

Example host-side extension for a multi-property rule:

```php
final class OrderRowClassExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('order_row_class', fn (Order $o): string =>
                $o->isPaid() && $o->isShipped() ? 'table-success'
                  : ($o->isRefunded() ? 'table-danger' : '')),
        ];
    }
}
```

## ADR-027 (progressive enhancement)

Zero JS. The class is server-rendered on the `<tr>`. Pure CSS does
the visual styling.
