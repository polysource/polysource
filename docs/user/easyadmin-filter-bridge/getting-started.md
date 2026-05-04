# `polysource/easyadmin-filter-bridge` — getting started

This guide walks you from `composer require` to a working,
session-persisted filter UI on an existing EasyAdmin v5 application.
By the end you'll have:

- the 8 enhanced built-in EA filters (presets, ranges, multi-select,
  nullable booleans, comparison whitelisting, …) — zero-config,
- 4 custom filter types (`BetweenDateFilter`, `InFilter`,
  `NotNullFilter`, `FullTextSearchFilter`) — opt-in per CRUD
  controller,
- an "Active filters" chips bar above the table — auto-rendered,
- session-persisted filter state across requests — auto-wired.

If you want the standalone primitive without EasyAdmin, see
[`polysource/filter` getting started](../filter/getting-started.md)
instead.

## Status

Pre-v0.1.0. The bridge is shipped from the same monorepo as
`polysource/filter` and depends on it. Contracts are stable per
[ADR-012](../../adr/0012-dual-product-positioning.md) (dual-product
positioning) and [ADR-013](../../adr/0013-filter-package-architecture.md)
(filter package architecture).

## 1. Prerequisites

| Component | Required |
|---|---|
| PHP | 8.4+ |
| Symfony | 7.4 LTS |
| EasyAdmin | 5.0+ (the bridge advertises `^4.24 \|\| ^5.0` but only 5.x is gated by CI at v0.1) |
| Twig | 3.x |
| Bootstrap | 5 (the chips bar markup uses Bootstrap 5 classes) |

If you're on EasyAdmin 4.24 the bridge should still work, but you
may run into edge cases around filter DTO shapes — please file an
issue. EA 6 support will arrive once a beta drops.

## 2. Install

```bash
composer require polysource/easyadmin-filter-bridge
```

Symfony Flex registers the bundle automatically. If you don't use
Flex, add it to `config/bundles.php`:

```php
return [
    // …
    Polysource\EasyAdminFilterBridge\PolysourceEasyAdminFilterBridgeBundle::class => ['all' => true],
    Polysource\Filter\PolysourceFilterBundle::class => ['all' => true],
];
```

That's it. The bridge prepends its form theme into `twig.form_themes`
and splices its views into the `@EasyAdmin` namespace via DI's
`prepend()`. Reload an EA index page where filters are configured,
and you should already see:

- presets ("Today", "Last 7 days", …) below datetime filters,
- quick-range buttons below numeric filters,
- a "Clear" button on datetime filters,
- chips above the table when any filter is applied.

No CRUD code change required.

## 3. The 8 enhancers — what they upgrade

The bridge replaces the form type of EA's built-in filters with a
strict-superset enhanced version. The replacement is transparent:
your existing `setFormTypeOption('label', …)` calls keep working.

| EA filter | Bridge option | What it does |
|---|---|---|
| `DateTimeFilter` | `presets: list<string>` | Renders preset buttons (today / yesterday / last 7 days / …). |
| `DateTimeFilter` | `show_clear: bool` (default true) | Shows a "Clear" button. |
| `NumericFilter` | `quick_ranges: list<{label, min?, max?}>` | One-click range presets (`< 50€`, `50–200€`, …). |
| `NumericFilter` | `step: float` | Forwarded to `<input step>`. |
| `ComparisonFilter` | `comparisons: list<string>` | Whitelists which comparison operators show up. |
| `BooleanFilter` | `include_null: bool` | Adds a "Null" radio for nullable booleans. |
| `ChoiceFilter` | `inline: bool` | Renders inline radios instead of a dropdown (host CSS). |
| `ArrayFilter` | `chip_display: bool` | Hook for host CSS/JS to render selected items as chips. |
| `EntityFilter` | `placeholder: ?string` | Placeholder for the autocomplete widget. |
| `TextFilter` | `min_length: int` | Adds `data-polysource--filter-min-length-value` for client-side validation. |

Full per-filter matrix with caveats: see
[`whats-new.md`](./whats-new.md).

Usage example in a `CrudController`:

```php
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(NumericFilter::new('price')
            ->setFormTypeOption('quick_ranges', [
                ['label' => '< 50€',     'min' => null, 'max' => 50],
                ['label' => '50–200€',   'min' => 50,   'max' => 200],
                ['label' => '200–400€',  'min' => 200,  'max' => 400],
            ]))
        ->add(DateTimeFilter::new('createdAt')
            ->setFormTypeOption('presets', ['today', 'last_7_days', 'this_month'])
            ->setFormTypeOption('show_clear', true));
}
```

## 4. The 4 custom filter types

Beyond the 8 enhancers, the bridge ships 4 fully new filter types
that hosts opt into per CRUD controller. They implement EA's
`FilterInterface` and ride the same `configureFilters()` hook.

### `BetweenDateFilter`

Strips EA's comparison dropdown to show only "from" and "to" date
pickers. Always emits `BETWEEN` with graceful fallback (only-lower
→ `>=`, only-upper → `<=`).

```php
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;

$filters->add(BetweenDateFilter::new('createdAt', 'Created'));
```

### `InFilter`

Multi-select choice picker emitting `IN (…)` (or `NOT IN (…)` if
you pass `negate: true`).

```php
use Polysource\EasyAdminFilterBridge\Filter\InFilter;

$filters->add(InFilter::new('status', 'Status')
    ->setFormTypeOption('choices', [
        'Draft'     => 'draft',
        'Published' => 'published',
        'Archived'  => 'archived',
    ]));
```

### `NotNullFilter`

Tri-state radio (Any / Has value / Empty) emitting `IS NULL` /
`IS NOT NULL`. Useful for nullable columns where "is the date
populated" is the question, not "what value".

```php
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;

$filters->add(NotNullFilter::new('archivedAt', 'Archive state'));
```

Custom labels:

```php
NotNullFilter::new('deletedAt')
    ->setFormTypeOption('labels', [
        'any' => 'All',
        'not_null' => 'Soft-deleted',
        'null' => 'Active',
    ]);
```

### `FullTextSearchFilter`

Single text input matched via `LIKE` across multiple configured
columns, `OR`-combined. Cheaper than a Meilisearch hookup when
data fits in one entity.

```php
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;

$filters->add(FullTextSearchFilter::new('q', 'Search')
    ->setFormTypeOption('properties', ['name', 'email', 'slug']));
```

The user's term is wrapped with `%…%` for substring matching.
Column names come from the DI-frozen `properties` option (host
code, never request data) — SQL injection is impossible.

## 5. Chips bar — auto-rendered

When any filter is applied, the bridge renders an "Active filters"
row above the table on every EA index page. Each chip shows
`<property>: <comparison> <value>` with operator-aware formatting:

- `BETWEEN`        → `Created at : 2026-01-01 → 2026-12-31`
- `IN` / `NOT IN`  → `Status : in draft, published`
- `IS [NOT] NULL`  → `Description state : is not null`
- else (`=`, `>=`, …) → `Price : >= 50`

Property names get humanised on the fly (`createdAt` → "Created at",
`is_active` → "Is active"). The X button strips that filter slice
from the URL via the `polysource--filter-chips` Stimulus
controller; a "Clear all" link nukes everything via EA's stock
`ea_url().unset('filters')` helper.

The chips bar is hidden when no filters are applied. To opt out
across the whole app:

```css
.polysource-filter-chips-bar { display: none !important; }
```

To customise the markup, override the override at the app level:

```twig
{# templates/bundles/EasyAdminBundle/crud/index.html.twig #}
{% extends '@!EasyAdmin/crud/index.html.twig' %}

{% block main %}
    {# your custom chips markup here, BEFORE parent() #}
    {{ parent() }}
{% endblock %}
```

Symfony's app-level template-override convention takes priority
over the bridge's bundle-level override.

## 5b. Subpanel mode — opt in per-controller

EasyAdmin renders filters in a centered Bootstrap modal. For lists
with many columns or analyst workflows where filters need to stay
reachable while you scan the table, that's awkward. Switch to a
right-anchored slide-in panel (480px wide, full-height) by
overriding the index template in `configureCrud()`:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;

public function configureCrud(Crud $crud): Crud
{
    return $crud->overrideTemplate(
        'crud/index',
        '@PolysourceEasyAdminFilterBridge/crud/index_subpanel.html.twig',
    );
}
```

Behaviour:

- Adds a `polysource-filter-subpanel` body class to the index page.
- Inline CSS re-positions EA's `#modal-filters` as an offcanvas-style
  panel + slides it in from the right edge.
- EA's modal lifecycle (focus trap, ESC, backdrop, AJAX form
  loading, apply/clear buttons) stays intact.

To customise width or animation, override the template again at
the app level and tweak the `<style>` block.

## 5c. Multi-group accordion in the filter form

For controllers with many filters, group them into named sections
to declutter the modal/subpanel:

```php
public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(BetweenDateFilter::new('archivedAt')
            ->setFormTypeOption('polysource_group', 'Dates'))
        ->add(InFilter::new('status', 'Status (multi)')
            ->setFormTypeOption('polysource_group', 'Lifecycle')
            ->setFormTypeOption('choices', [...]))
        ->add(NotNullFilter::new('description')
            ->setFormTypeOption('polysource_group', 'Lifecycle'))
        // No group — renders flat at the top.
        ->add(FullTextSearchFilter::new('q')
            ->setFormTypeOption('properties', ['name', 'description']));
}
```

Filters declaring `polysource_group` render inside `<details>`
accordion sections (first group `open`, rest collapsed) with a
count badge. Filters without a group render flat above the
accordions. Works identically in integrated and subpanel modes.

Two pieces wire it through: a FormTypeExtension widens every form
type's `OptionsResolver` to accept `polysource_group` (so EA's
stock filter form types don't crash on the unknown option), and a
`GroupCarrierConfigurator` copies the value into the FilterDto's
`customOptions` so the Twig override can read it back when
rendering the accordion. Hosts don't have to think about either.

## 6. Session persistence — auto-wired

The bridge stores active filters in the HTTP session under
`polysource.filter.{xxh128(controller-fqcn)}`. Reloading the page,
navigating back from a detail view, or following a bookmark
restores the last-applied filters automatically. No URL noise.

The session subscriber hooks
`BeforeCrudActionEvent`. The session id is the CRUD controller
FQCN — different controllers remember different filter states.

To clear a controller's stored filters programmatically:

```php
public function __construct(
    private readonly Polysource\Filter\Service\FilterService $filters,
) {}

public function clearMyController(): void
{
    $this->filters->clear(MyCrudController::class);
}
```

## 7. Override the form theme

The bridge ships a form theme at
`@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig`
that delegates to upstream EA blocks via `{% use '@EasyAdmin/...' %}`.
To replace it (e.g. to render quick ranges differently):

```yaml
# config/packages/twig.yaml
twig:
    form_themes:
        - 'forms/my-bridge-theme.html.twig'  # before the bridge theme
```

Or per-controller:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud->setFormThemes(['forms/my-bridge-theme.html.twig']);
}
```

## 8. Add a custom filter type

If the 8 enhancers + 4 customs don't cover your case, register a new
filter type via the [`polysource/filter` 3-tag pipeline](../filter/getting-started.md#6-custom-filter-types).
The bridge consumes the same registry, so anything you register
there is available to your EA controllers via
`setFormTypeOption('form_type', YourFormType::class)`.

## What's deferred to v0.2+

- **Tab-style multi-group rendering.** Today multi-group renders
  as `<details>` accordions in the EA modal/subpanel. Tab
  rendering exists in `polysource/filter`'s standalone subpanel
  template but isn't yet wired through to EA — adding it would
  mean re-implementing EA's filter form template completely. Open
  for v0.2 if user feedback wants it.
- **Saved-filter UX** (a la "My drafts" / "My pending review"
  stored presets per user).
- **A query serializer** so links can carry filter state without
  a session (today the form roundtrips through GET, but
  persistent links require a tiny encoder).
- **Datasource lifecycle** (`Factory → Builder → Loader`) for
  hosts wanting to compose multiple data sources in one
  Resource — see [ADR-014](../../adr/0014-datasource-lifecycle-deferred.md).

## See also

- [`whats-new.md`](./whats-new.md) — honest per-filter matrix vs
  upstream EA, with caveats.
- [ADR-012](../../adr/0012-dual-product-positioning.md) — why the
  bridge exists at all (vs forking EasyAdmin).
- [ADR-013](../../adr/0013-filter-package-architecture.md) — the
  primitive that powers the bridge's chips/subpanel/persistence.
- [`polysource/filter` getting started](../filter/getting-started.md)
  — for non-EasyAdmin Symfony controllers.
