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
| PHP | `>=8.1` (any 8.1+, 9.x forward-compat) |
| Symfony | `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` (every minor since 5.4, LTS + non-LTS) |
| EasyAdmin | `^4.24 \|\| ^5.0` |
| Doctrine ORM | `^2.20 \|\| ^3.6` |
| Twig | `^3.0` |
| Bootstrap | 5 (the chips bar markup uses Bootstrap 5 classes) |

The bridge advertises **the same constraints as `easycorp/easyadmin-bundle` 4.29**
(`php: >=8.1`, `symfony/*: ^5.4|^6.0|^7.0|^8.0`) so any host that can install
EA 4 can install the bridge. Composer's resolver picks the right combination
automatically — a host on PHP 8.1 + Sf 5.4 will get EA 4 (EA 5 itself
requires PHP 8.2+); a host on PHP 8.4 + Sf 7.4 will get EA 5.

**Note on Sf 5.4** — only the bridge + the standalone `polysource/filter`
primitive support Sf 5.4. `polysource/symfony-bundle` (the standalone
admin engine, unrelated to the bridge) requires Sf 6.4+ because it
uses `ValueResolverInterface` (Sf 6.2+). The bridge audience is unaffected:
the bridge doesn't depend on `symfony-bundle`.

The bridge is gated by CI on 5 explicit combos covering the realistic
profiles of EA-using Symfony apps in 2026 (cf.
[ADR-015](../../adr/0015-multi-version-compatibility-baseline.md)):

- PHP 8.1 + Symfony 5.4 + EA 4.x (legacy stack)
- PHP 8.2 + Symfony 6.4 + EA 4.x (mainstream 2024-2025)
- PHP 8.2 + Symfony 6.4 + EA 5.x (bridge transfer audience)
- PHP 8.3 + Symfony 7.4 + EA 5.x (modern)
- PHP 8.4 + Symfony 7.4 + EA 5.x (bleeding-edge / our dev local)

EA 6 support will arrive once a beta drops.

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

### Value resolution: 5-stage chain

The chip's value text is resolved by `ChipValueFormatter` via a
5-stage chain, from most specific to most generic:

1. **Filter chipFormatter** — declared via
   `Polysource::filter($f)->chipFormatter(...)`. Highest
   priority, always wins. Accepts an inline callable OR a
   service implementing `ChipFormatterInterface` (cf. ADR-016).
2. **Field chipFormatter** — declared via
   `Polysource::field($f)->chipFormatter(...)` on the matching
   field in `configureFields()`. Same accepted shapes (callable
   or `ChipFormatterInterface`). Enables **table↔chip
   coherence**: ONE callable, both layers consume it. The chip
   never disagrees with the column.
3. **Match by `FilterDto::getFormType()`** — covers EA
   built-ins AND custom `FilterInterface` impls that use
   EA form types (e.g. a custom `IsSentFilter` setting
   `setFormType(BooleanFilterType::class)` gets the boolean
   Yes/No translation automatically).
4. **Auto-detect Doctrine association** — when the filter's
   property maps to a `ManyToOne` / `OneToMany` association
   on the current entity, the chip resolves the value as
   the entity's `__toString()`. Covers custom
   `AssociationByIdFilter`-style filters that filter on
   relations without using EA's `EntityFilter`.
5. **Default stringify** — defensive fallback: scalars
   cast verbatim, arrays joined with commas, objects emit
   empty string.

### Field-level coherence example

```php
// In configureFields()
yield Polysource::field(BooleanField::new('isVisible'))
    ->chipFormatter(static fn (mixed $v): string =>
        true === $v || '1' === $v ? '👁️ Visible' : '🚫 Caché');
```

→ The table column shows the boolean as "👁️ Visible" / "🚫 Caché"
AND the chip displays the same text when the host filters by
`isVisible`. No coercion, no second declaration.

### Service-based chip formatter (`ChipFormatterInterface`)

Inline closures are great for one-off cases, but break down once
the formatter needs DI (Translator, EntityManager, business
services), reuse across multiple controllers, or isolated unit
tests. For those cases, implement
`Polysource\Filter\Bridge\Contract\ChipFormatterInterface` —
shipped from the tronc commun (`polysource/filter`) so future
Sonata or API Platform bridges accept the same contract.

```php
namespace App\ChipFormatter;

use Polysource\Filter\Bridge\Contract\ChipFormatterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class VisibilityChipFormatter implements ChipFormatterInterface
{
    public function __construct(private readonly TranslatorInterface $translator) {}

    public function format(mixed $rawValue): string
    {
        $isShown = true === $rawValue || '1' === $rawValue || 1 === $rawValue;

        return $this->translator->trans(
            $isShown ? 'visibility.shown' : 'visibility.hidden',
        );
    }
}
```

Inject and pass it like a regular service:

```php
// CategoryCrudController.php
public function __construct(
    private readonly VisibilityChipFormatter $visibilityChipFormatter,
) {}

public function configureFields(string $pageName): iterable
{
    yield Polysource::field(BooleanField::new('isVisible'))
        ->chipFormatter($this->visibilityChipFormatter);
}
```

Run the demo to see both styles side-by-side: `ProductCrudController`
keeps an inline closure, `CategoryCrudController` uses the service
shape (cf. `examples/easyadmin-bridge-demo/`).

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

## 5c. Filter organisation: tabs + groups

For controllers with many filters, organise them via the
`Polysource` fluent facade — modelled after EA's own
`FormField::addTab()` pattern but with a 2-level hierarchy
(tab > group > filter).

### Per-filter declaration

```php
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;

return $filters
    ->add(Polysource::filter(BetweenDateFilter::new('archivedAt'))
        ->tab('Dates')
        ->group('Archive'))
    ->add(Polysource::filter(InFilter::new('status', 'Status (multi)'))
        ->tab('Lifecycle')
        ->group('Status')
        ->setFormTypeOption('choices', [...]))
    ->add(NotNullFilter::new('description'));     // ungrouped
```

### Marker mode (sequential, EA-tabs ergonomics)

```php
return $filters
    ->add(TextFilter::new('name'))                // top-level ungrouped
    ->add(Polysource::tab('Visibility'))          // marker → tab starts
    ->add(Polysource::group('Active state'))      // marker → group within
    ->add(BooleanFilter::new('isVisible'))        // inherits both
    ->add(BooleanFilter::new('isPublished'))      // inherits both
    ->add(Polysource::tab('Dates'))               // new tab → group resets
    ->add(DateTimeFilter::new('createdAt'));      // tab="Dates", no group
```

Per-filter explicit `Polysource::filter($f)->tab(...)` always
overrides marker inheritance. The two styles can be mixed
freely.

### Rendering

A Stimulus controller (`polysource--filter-modal-layout`) reads
the tabs/groups tree (built server-side from each filter's
customOptions) and reorganises EA's AJAX-loaded filter form into:

- **Top-level ungrouped** filters → flat at the top of the modal
- **Top-level groups** → `<details>` accordions
- **Tabs** → Bootstrap nav-tabs strip with nested `<details>`
  accordions per group inside each tab pane

Zero visual change vs upstream EA when no tab/group is declared
— the controller falls through to flat rendering.

### Storage

All metadata lives in EA's native `FilterDto::customOptions`
(via the bridge's `BridgeOptions::TAB` / `BridgeOptions::GROUP`
constants). No `formTypeOptions` pollution, no global
FormTypeExtension — matches EA's own internal pattern (cf.
`LanguageFilter::useAlpha3Codes()`).

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
