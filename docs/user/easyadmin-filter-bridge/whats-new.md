# `polysource/easyadmin-filter-bridge` — What's actually new vs EasyAdmin v5

This page is the authoritative answer to "what does the bridge add that
upstream EasyAdmin v5 doesn't already do?" — written without marketing
spin so you can decide whether the bridge is worth installing in your
stack.

## TL;DR

The bridge adds **one extra option per filter type** (or a small handful)
plus a **single Stimulus controller** that wires preset / quick-range /
clear interactions. The visual styling is **100% upstream EasyAdmin** —
the bridge form theme delegates to the upstream `ea_*_filter_widget`
blocks via `{{ block('…') }}` and only wraps them in a `<div
data-controller="polysource--filter">` carrying the new options as
`data-*-value` attrs. If the host app has customised the upstream
theme, the bridge inherits those customisations automatically.

## Per-filter matrix

| Filter type | Bridge option | What it does | Upstream already? | Stimulus action |
|---|---|---|---|---|
| **`TextFilter`** | `min_length: int` | Adds `data-polysource--filter-min-length-value` to the wrapper. Intended for client-side validation. | No. | None shipped — host wires its own listener. |
| **`NumericFilter`** | `step: float` | Forwarded to `<input step>`. | Partially (you can pass `step` via `value_type_options`, but the bridge surfaces it as a top-level option for symmetry). | None shipped. |
| | `quick_ranges: list<{label, min?, max?}>` | Renders a row of `<button>` below the inputs. Clicking fills `value` (and `value2` if both bounds set), then sets the `comparison` select to `between`/`>=`/`<=` accordingly. | **No.** | `applyQuickRange` ✓ |
| **`ComparisonFilter`** | `comparisons: list<string>` | Whitelists which comparison operators show up in the dropdown (e.g. `['=', '>=', '<=']`). | **No.** Upstream always exposes the full list including `between`, even when `between` is meaningless for `ComparisonFilter` (which has no `value2`). | None — applied at render time via Symfony's `choice_filter`. |
| **`DateTimeFilter`** | `presets: list<string>` | Renders a row of `<button>` below the date inputs. Recognised presets: `today`, `yesterday`, `last_7_days`, `last_30_days`, `this_month`, `last_month`, `this_year`. Clicking computes the date(s) and fills `value`/`value2` with the right format for the input type. | **No.** | `applyPreset` ✓ |
| | `show_clear: bool` | Adds a "Clear" `<button>` that empties `value` and `value2`. | No. | `clearValues` ✓ |
| **`BooleanFilter`** | `include_null: bool` | Adds a third "Null" radio choice so you can filter rows where the boolean is NULL (common with nullable `boolean` columns). | **No** — upstream is hard-coded to true/false only. | None shipped. |
| **`ChoiceFilter`** | `inline: bool` | Adds a `data-polysource--filter-inline-value` attr. Intended as a hint that the host theme can use to render choices as inline radios instead of a dropdown. | Partially (you can already pass `expanded: true` via `value_type_options`). | None shipped. |
| **`ArrayFilter`** | `chip_display: bool` | Adds a `data-polysource--filter-chip-display-value` attr. Intended as a hook for host CSS/JS to render selected items as removable chips. | No. | None shipped — host wires its own UI. |
| **`EntityFilter`** | `placeholder: ?string` | Adds a `data-polysource--filter-placeholder-value` attr. Intended for the autocomplete/select widget. | Partially (placeholder can be passed via `value_type_options`). | None shipped. |

## Honest summary of what's **not** in v0.1

- **No JS for `min_length`, `inline`, `chip_display`, `placeholder`.** The
  bridge surfaces those options as `data-*` attrs on the wrapper but
  doesn't ship a Stimulus action for them. Host apps that want them to
  do something (chip rendering, client-side length validation, etc.)
  write their own controller or CSS. The data attrs are the contract;
  the behaviour is your call.

- **No new comparison operators.** `between`, `not between`, `>=`, etc.
  all come from upstream `ComparisonType`. The bridge can only
  *whitelist* a subset, not add new ones.

- **No autocomplete UX upgrades.** The `EntityFilter`'s autocomplete is
  whatever upstream EA renders.

- **No multi-select tag input.** `ArrayFilter`'s `chip_display` is just a
  hook — chip rendering is on the host.

## What you actually get out of the box

```php
// host app's CrudController
->add(NumericFilter::new('price')
    ->setFormTypeOption('quick_ranges', [
        ['label' => '< 50€', 'min' => null, 'max' => 50],
        ['label' => '50–200€', 'min' => 50, 'max' => 200],
        ['label' => '200–400€', 'min' => 200, 'max' => 400],
    ]))
```

Renders + works (clicking a button fills inputs, sets comparison to
`between`/`>=`/`<=`, the second input becomes visible if `between`).
**No JS to write, no theme override.**

```php
->add(DateTimeFilter::new('createdAt')
    ->setFormTypeOption('presets', ['today', 'last_7_days', 'this_month'])
    ->setFormTypeOption('show_clear', true))
```

Same: clicking "Last 7 days" fills the two date inputs to today − 6 and
today, sets comparison to `between`, reveals the second date picker.
"Clear" empties both. Out of the box.

## Where to go for more

- [Roadmap](../../roadmap/development-plan.md) — what's planned for
  v0.2 and beyond (chip-display Stimulus, autocomplete enhancements,
  saved-filter UX).
- [ADR-012](../../adr/0012-dual-product-positioning.md) — why the bridge
  exists at all (vs forking EasyAdmin).
