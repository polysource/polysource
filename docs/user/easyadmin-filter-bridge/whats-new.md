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

Since v0.1 the bridge also delegates **session persistence** to
[`polysource/filter`](../filter/getting-started.md) under the hood,
and exposes the standalone primitive's surface to host apps that want
it: a chips bar above the list, an opt-in side subpanel UI, and
multi-group accordion/tab rendering. None of those replace EasyAdmin's
own UI — they are **additive**, opt-in via per-controller config, and
keep the upstream layout intact when not enabled.

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

## Beyond per-filter options — what the bridge layers on top

The matrix above lists per-filter enhancements. The bridge also wires
three **list-level** capabilities on top of EasyAdmin's filter
sidebar, all powered by `polysource/filter`:

### Chips bar above the list

When filters are applied the bridge renders a row of removable
chips above the list (override template at
`Resources/views/crud/index.html.twig` in the bridge). Each chip
shows `<property>: <comparison> <value>` with operator-aware
formatting (`BETWEEN` shows `v1 → v2`, `IN` shows `v1, v2, …`,
`IS [NOT] NULL` shows the predicate). Clicking the × strips that
filter slice from the URL via the
`polysource--filter-chips` Stimulus controller; a "Clear all"
link nukes everything via EA's stock `ea_url().unset('filters')`.

The chips bar renders automatically on every EA index page where
filters are applied. Hosts that don't want it can hide it via CSS
(`.polysource-filter-chips-bar { display: none }`) or override
`crud/index.html.twig` at the app level.

### Side subpanel mode (opt-in)

EasyAdmin renders filters in a centered Bootstrap modal. For lists
with many columns or for analyst-style workflows where filters
need to stay reachable while you scan the table, that's awkward.
Opt in to subpanel mode by overriding the index template in your
`configureCrud()`:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud->overrideTemplate(
        'crud/index',
        '@PolysourceEasyAdminFilterBridge/crud/index_subpanel.html.twig',
    );
}
```

The bridge then re-positions EA's `#modal-filters` as a
right-anchored slide-in panel (480px wide, full-height, slide-in
animation) via inline CSS keyed off a `polysource-filter-subpanel`
body class. EA's modal lifecycle (focus trap, ESC, backdrop, AJAX
form loading, apply/clear buttons) stays intact — only the
positioning + animation change.

The default mode is `integrated` — i.e. exactly EA's modal — so
existing apps see no change unless they explicitly opt in.

### Filter organisation: tabs + groups (2-level hierarchy)

The bridge ships a fluent facade for organising filters into
**tabs** and **groups** — modelled after EA's own
`FormField::addTab()` / `addFieldset()` pattern for forms, but
extended to a 2-level hierarchy (tab > group > filter).

**Per-filter declaration:**

```php
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;

return $filters
    ->add(Polysource::filter(BetweenDateFilter::new('archivedAt'))
        ->tab('Dates')
        ->group('Archive'))
    ->add(Polysource::filter(InFilter::new('status'))
        ->tab('Lifecycle')
        ->group('Status')
        ->setFormTypeOption('choices', [...]));
```

**Marker mode** (sequential, EA-tabs ergonomics):

```php
return $filters
    ->add(TextFilter::new('name'))                    // top-level ungrouped
    ->add(Polysource::tab('Visibility'))              // marker: tab starts
    ->add(Polysource::group('Active state'))          // marker: group within
    ->add(BooleanFilter::new('isVisible'))            // inherits both
    ->add(BooleanFilter::new('isPublished'))          // inherits both
    ->add(Polysource::tab('Dates'))                   // new tab → group resets
    ->add(DateTimeFilter::new('createdAt'));          // tab="Dates", no group
```

Per-filter explicit declarations always override marker
inheritance.

**Rendering** (Stimulus `polysource--filter-modal-layout`):
- **Top-level ungrouped** filters render flat at the top
- **Top-level groups** render as `<details>` accordions
- **Tabs** render as Bootstrap nav-tabs with nested `<details>`
  accordions for groups inside each tab
- Empty/no-tabs/no-groups → flat layout (zero visual change vs
  upstream EA)

**Storage**: 100% via EA's native `customOptions` channel
(`BridgeOptions::TAB`, `BridgeOptions::GROUP`). No
`formTypeOptions` pollution, no global FormTypeExtension —
matches EA's own internal pattern (cf.
`LanguageFilter::useAlpha3Codes()` writing to customOptions).

This solves the "30 filters scrolling forever" problem that EA
modals hit on rich resources.

### Session persistence (always on)

The bridge stores active filters in the HTTP session under
`polysource.filter.{xxh128(controller-fqcn)}`. Reloading the page,
navigating back from a detail view, or following a bookmark restores
the last applied filters automatically. No URL noise.

Cleared via the upstream EA "Reset" button (which the bridge
intercepts and translates to a `FilterService::clear()` call).

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

## Multi-version support since v0.1

The bridge is intentionally low-bar to install: PHP 8.1+, Symfony
5.4 LTS|6.4 LTS|7.4 LTS, EasyAdmin 4.24+|5.0+, Doctrine ORM
2.20+|3.6+. Five explicit CI combos run on each push (cf.
[ADR-015](../../adr/0015-multi-version-compatibility-baseline.md)) so
the realistic profiles of EA-using Symfony apps in 2026 are gated.

## ChipFormatterInterface (since v0.1)

Beside inline `chipFormatter()` callables, the bridge accepts service
objects implementing
`Polysource\Filter\Bridge\Contract\ChipFormatterInterface` (cf.
[ADR-016](../../adr/0016-bridge-contracts-shared-with-polysource-filter.md)).
The contract lives in `polysource/filter`, so future Sonata or API
Platform bridges accept the same formatter without changes. Use the
service shape when you need DI, cross-controller reuse, or isolated
unit tests.

## Where to go for more

- [`polysource/filter` getting-started](../filter/getting-started.md) —
  the standalone primitive the bridge composes. Useful if you want
  the same chips/subpanel/multi-group UI in a non-EasyAdmin
  controller, or if you want to add a custom filter type and
  understand the 3-tag pipeline.
- [Roadmap](../../roadmap/development-plan.md) — what's planned for
  v0.2 and beyond (chip-display Stimulus, autocomplete enhancements,
  saved-filter UX).
- [ADR-012](../../adr/0012-dual-product-positioning.md) — why the bridge
  exists at all (vs forking EasyAdmin).
- [ADR-013](../../adr/0013-filter-package-architecture.md) — the
  architecture the bridge sits on top of.
