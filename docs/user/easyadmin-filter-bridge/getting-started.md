# `polysource/easyadmin-filter-bridge` — getting started

This guide walks you from `composer require` to a working,
session-persisted filter UI on an existing EasyAdmin application
(4.24+ or 5.0+).
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

**Shipped — v0.5.7 (2026-05-15).** Public API release-candidate stable per
[ADR-012](../../adr/0012-dual-product-positioning.md) (dual-product
positioning) and [ADR-013](../../adr/0013-filter-package-architecture.md)
(filter package architecture). The bridge is shipped from the same monorepo as
`polysource/filter` and depends on it (transitively).

## 1. Prerequisites

### PHP / Symfony / EasyAdmin stack

| Component | Required |
|---|---|
| PHP | `>=8.2` (any 8.2+, 9.x forward-compat) |
| Symfony | `^6.4 \|\| ^7.0 \|\| ^8.0` (LTS + non-LTS since 6.4) |
| EasyAdmin | `^4.24 \|\| ^5.0` |
| Doctrine ORM | `^2.20 \|\| ^3.6` |
| Twig | `^3.0` |
| Bootstrap | 5 (the chips bar markup uses Bootstrap 5 classes) |

### Stimulus (optional — progressive enhancement only)

Every feature has a server-side baseline (since v0.2.0, per
[ADR-027 progressive enhancement](../../adr/0027-progressive-enhancement.md)).
The bridge ships ONE optional Stimulus controller
(`polysource--filter`) that enhances the filter widgets in place;
without it, everything still works through plain links, forms, and
native `<details>` elements.

| Feature | Needs Stimulus? |
|---|---|
| Chips bar render | no (server-rendered HTML) |
| Chip × close buttons | no (server-driven links; enhanced in place when JS is present) |
| Chip text via `chipFormatter()` | no (server-rendered) |
| Custom filter types (`BetweenDateFilter`, `InFilter`, `NotNullFilter`, `FullTextSearchFilter`) | no |
| Session persistence | no |
| `placeholder` on `EntityFilter` | no (HTML attribute only) |
| Tab + group layout (multi-tab filter modal) | no (native `<details name>` accordions + CSS `:has()` pairing — zero JS) |
| Subpanel mode (right-anchored slide-in) | no (CSS-only restyling of EA's modal) |
| The `polysource--filter` controller itself | value-only since v0.2.0 — it exposes typed data attrs (`step`, `min_length`, `include_null`, …) for host-side JS layers to read; on its own it changes nothing |

**Auto-discovery is already wired** — the bridge declares its
controller in `assets/package.json symfony.controllers` with an
explicit `name` override that preserves the short identifier
(`polysource--filter`). Both Symfony UX discovery bundles honor
this manifest:

- **Webpack Encore + `@symfony/stimulus-bridge`**: zero-config.
  `composer require polysource/easyadmin-filter-bridge` is enough.
- **AssetMapper + `@symfony/stimulus-bundle`**: add the package to
  your host's `assets/controllers.json` (one-time, until a Flex
  recipe lands in `symfony/recipes-contrib`):

  ```json
  {
      "controllers": {
          "@polysource/easyadmin-filter-bridge": {
              "polysource--filter": { "enabled": true }
          },
          "@polysource/filter": {
              "polysource--filter-modal-layout": { "enabled": true },
              "polysource--filter-chips":        { "enabled": true },
              "polysource--filter-subpanel":     { "enabled": true }
          }
      }
  }
  ```

If your host has **no Stimulus pipeline at all** (classic Webpack
Encore with manual `.addEntry()` declarations, plain jQuery /
vanilla JS frontend), some interactive features render as
visible-but-inert UI — currently the chip × close buttons and the
tab/group filter modal layout. The recommended path is to install
`@symfony/stimulus-bundle` or `@symfony/stimulus-bridge`. The
server-side surface (chips bar render, session persistence, custom
filter types, chip text formatting) works without Stimulus.

Per ADR-027 v0.2.0+ progressively retrofits the remaining
interactive features so the baseline works fully server-side — this
section will eventually report "no Stimulus required".

The bridge does NOT degrade silently — it renders the data
attributes the controllers expect, so once Stimulus is added
later, the existing host code starts working without changes.

### CI / version matrix

The bridge advertises **the same constraints as `easycorp/easyadmin-bundle` 4.29**
(`php: >=8.2`, `symfony/*: ^6.4|^7.0|^8.0`) so any host that can install
EA 4 can install the bridge. Composer's resolver picks the right combination
automatically — a host on PHP 8.2 + Sf 6.4 will get EA 4 (EA 5 itself
requires PHP 8.2+); a host on PHP 8.4 + Sf 7.4 will get EA 5.

**Note on Sf 5.4** — only the bridge + the standalone `polysource/filter`
primitive support Sf 5.4. `polysource/symfony-bundle` (the standalone
admin engine, unrelated to the bridge) requires Sf 6.4+ because it
uses `ValueResolverInterface` (Sf 6.2+). The bridge audience is unaffected:
the bridge doesn't depend on `symfony-bundle`.

The bridge is gated by CI on 5 explicit combos covering the realistic
profiles of EA-using Symfony apps in 2026 (cf.
[ADR-015](../../adr/0015-multi-version-compatibility-baseline.md)):

- PHP 8.2 + Symfony 6.4 LTS + EA 4.x (legacy stack)
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

### 2a. Multi-kernel apps

If your app boots multiple Symfony kernels from one composer
project (typical "shared backend + per-API gateway" layouts) and
only one of them loads EasyAdmin, the bridge is **safe to keep
registered globally** — since v0.5.7, the bundle's
`Extension::load()`, `prepend()` and `Bundle::boot()` all
short-circuit on kernels where `EasyAdminBundle` isn't loaded.
Services are only registered where they can wire.

If your kernel layout uses per-app `bundles.php` files (e.g.
`apps/<name>/config/bundles.php`), you can still scope the bridge
to just the EA-aware kernel if you prefer — both styles are
supported:

```php
// apps/backend/config/bundles.php  (channel-scoped)
return [
    // …
    Polysource\Filter\PolysourceFilterBundle::class               => ['all' => true],
    Polysource\EasyAdminFilterBridge\PolysourceEasyAdminFilterBridgeBundle::class => ['all' => true],
];
```

### 2b. Multi-tenant route prefixes (e.g. `/{channel}/admin`)

If your host mounts EA under a non-default prefix (typically
multi-tenant apps where every admin URL is channel-scoped like
`/{channel}/admin/...`), the bridge's controllers — which
hard-code `#[Route('/admin/...')]` — must be imported under
your prefix, not at the bare `/admin/...` root. Otherwise
generated links (export, matching-count, column preferences,
filter share, …) would escape the tenant namespace.

**Opt out of auto-registration** and import the routes
manually under your own prefix:

```yaml
# config/packages/polysource_easyadmin_filter_bridge.yaml
polysource_easyadmin_filter_bridge:
    auto_register_routes: false
```

```yaml
# config/routes/polysource.yaml
polysource_easyadmin_filter_bridge:
    resource: '@PolysourceEasyAdminFilterBridgeBundle/Resources/config/routes.php'
    type: php
    prefix: '/%channel%'   # wherever EA is mounted in your host
```

After cache clear, `debug:router` should now show:

```
polysource_export   GET  /{channel}/admin/polysource/export/{resource}.{format}
```

Single-tenant installs (the common case) need none of this —
the default `auto_register_routes: true` keeps zero-config working.

### 2c. Database schema (REQUIRED if Doctrine is wired)

The bridge stores 5 things in your database when those features are
used:

| Table | Feature | Since |
|---|---|---|
| `polysource_saved_views` | Saved views dropdown | v0.1.0 |
| `polysource_column_preferences` | Per-user column visibility & order | v0.3.0 |
| `polysource_bulk_action_history` | Bulk-action audit log | v0.5.0 |
| `polysource_recent_records` | "Recently viewed" widget | v0.5.0 |
| `polysource_filter_url_tokens` | Short shareable filter URLs | v0.5.0 |

The bundle ships the Doctrine Entity classes; **your app owns the
migrations**. Run them like any other schema change in your app:

```bash
# Generate a migration from the new entities
php bin/console doctrine:migrations:diff

# Apply it
php bin/console doctrine:migrations:migrate
```

Or, in a demo / dev sandbox, push the schema directly:

```bash
php bin/console doctrine:schema:update --force --complete
```

**MySQL caveat — DDL implicit commit.** If `doctrine:migrations:migrate`
reports success but the tables don't actually appear, you're hitting
the MySQL implicit-commit-on-DDL issue (the migration transaction is
rolled back but the in-memory state thinks it succeeded). Two fixes:

1. Run the SQL directly via `dbal:run-sql` (paste each statement from
   `doctrine:schema:update --dump-sql`), or
2. Set `transactional: false` on the affected migration via
   `protected function isTransactional(): bool { return false; }`.

Upgrading from an older polysource lineage? Run
`doctrine:migrations:diff` again — it picks up only the new columns
and tables, doesn't recreate what's already there.

### Graceful degradation when the schema is missing

If you skip the migration step, the bridge's saved-views dropdown
silently disappears from EA index pages rather than 500'ing every
admin page (since v0.5.7). This is a SAFETY NET — the bundle isn't
meant to be used without the schema. Run the migration.

## 3. The 8 enhancers — what they upgrade

The bridge replaces the form type of EA's built-in filters with a
strict-superset enhanced version. The replacement is transparent:
your existing `setFormTypeOption('label', …)` calls keep working.

| EA filter | Bridge option | What it does |
|---|---|---|
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\ComparisonFilter;

public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(NumericFilter::new('price')->setFormTypeOption('step', 0.01))
        ->add(ComparisonFilter::new('reorderLevel')
            ->setFormTypeOption('comparisons', ['=', '>=', '<=']))
        ->add(DateTimeFilter::new('createdAt'));
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
{% extends '@PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}

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
