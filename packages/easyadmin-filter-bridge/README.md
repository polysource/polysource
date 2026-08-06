# polysource/easyadmin-filter-bridge

> Drop-in package that **enriches the filters of an existing EasyAdmin
> app (4.24+ or 5.0+)** without forking EasyAdmin. Plugs into EasyAdmin's
> `FilterConfiguratorInterface` extension point.

## Status

**v0.9.1 published (2026-08-06).** API release-candidate stable —
committed for v0.9.x, breaking changes allowed before v1.0 (cf.
[ADR-012](../../docs/adr/0012-dual-product-positioning.md)).
Distributed on Packagist as
[`polysource/easyadmin-filter-bridge`](https://packagist.org/packages/polysource/easyadmin-filter-bridge).

Feature-complete on the bridge side, dogfooded on multi-tenant
client integrations since v0.5.7:

- **All 8 built-in EasyAdmin filters covered** by an Enhancer
  (`DateTime`, `Boolean`, `Text`, `Numeric`, `Choice`, `Comparison`,
  `Array`, `Entity`).
- **4 custom filter types**: `BetweenDateFilter`, `InFilter`,
  `NotNullFilter`, `FullTextSearchFilter`.
- **Twig templates auto-register** via `PrependExtensionInterface` —
  enhanced widget HTML renders with zero config.
- **Filter session persistence** via `FilterSessionPersistenceSubscriber`
  on `BeforeCrudActionEvent` — operators returning to the index page
  see their previous filters restored automatically (scoped per CRUD
  controller FQCN, no leak across resources).
- **8 polysource routes auto-import** via `Bundle::boot()` since
  v0.5.4 — no manual `routes.yaml` import needed in the host.
- **Multi-kernel safe** since v0.5.7 — bundle is a no-op on
  EA-less kernels.
- **Multi-tenant ready** since v0.5.7 — opt out of auto-route
  registration via `auto_register_routes: false` to mount under
  a custom prefix (e.g. `/{channel}/admin`).

## What it does

Once installed, EasyAdmin's built-in filters gain richer form types,
**without any change to your existing CRUD controllers**:

| Built-in filter | Enhancement |
|---|---|
| `DateTimeFilter` | Dedicated block prefix (`polysource_enhanced_datetime_filter`) for theme overrides. |
| `BooleanFilter` | Optional `include_null` flag — adds a third "Empty / Null" choice to filter rows where the column is `NULL`. |
| `TextFilter` | Optional `min_length` flag — skip filter for input shorter than the threshold (default 0 = no threshold). |
| `NumericFilter` | `step` option (granularity hint, e.g. `0.01` for currency). |
| `ChoiceFilter` | `inline` option — render choices as pills/badges instead of dropdown. |
| `ComparisonFilter` | `comparisons` option — whitelist of operators to expose in the dropdown (default `[]` = all). |
| `ArrayFilter` | `chip_display` option — selected items as removable chips instead of multi-line list. |
| `EntityFilter` | `placeholder` option — custom placeholder text for the dropdown / autocomplete. |

Plus, list-level capabilities layered on top:
- Filter **chips/tags** bar above the table (active filters visible, click X to remove).
- **Session persistence** of filters per CRUD controller FQCN.
- **Saved views** dropdown (private / team / public scopes).
- **Column visibility** dropdown + **column reordering**.
- **Filter-aware streaming export** (CSV / XLSX).
- **Matching-count** JSON endpoint for bulk dry-run preview.
- **Filter URL tokens** for short shareable filtered URLs.
- Custom filter types: `BetweenDateFilter`, `InFilter`, `NotNullFilter`, `FullTextSearchFilter`.

## Installation

```bash
composer require polysource/easyadmin-filter-bridge
```

The bundle auto-registers via Symfony Flex. If you don't use Flex,
add it manually to `config/bundles.php`:

```php
return [
    // …
    Polysource\EasyAdminFilterBridge\PolysourceEasyAdminFilterBridgeBundle::class => ['all' => true],
];
```

**Zero configuration needed.** As soon as the bundle is loaded, the
shipped Configurators auto-tag themselves via EasyAdmin's
`registerForAutoconfiguration(FilterConfiguratorInterface::class)`,
and EasyAdmin's `FilterFactory` picks them up to mutate filter DTOs
right after they are created.

### Frontend assets — server-rendered first, Stimulus optional

**The filter modal tabs, group accordions, and the chips bar are
server-rendered — zero JavaScript required** (since v0.2.0, per
[ADR-027 progressive enhancement](../../docs/adr/0027-progressive-enhancement.md)).
Tabs use native `<details name="...">` exclusive accordions, pane
switching is pure CSS, and every chip's × button is a plain link.

Two kinds of assets ship with the bundle:

1. **A stylesheet + a small defensive script** in `Resources/public/`,
   published to `public/bundles/polysourceeasyadminfilterbridge/` by
   `assets:install` (Symfony Flex runs it automatically). The bridge's
   index template links them itself — nothing to wire. Theming is done
   through `--polysource-*` CSS variables — see
   [the theming guide](../../docs/user/easyadmin-filter-bridge/theming.md).
2. **One optional Stimulus controller** (`polysource--filter`, under
   `assets/controllers/`) that progressively enhances the filter
   widgets: preset buttons, quick-ranges, clear buttons, validation
   hints. Hosts using AssetMapper or Webpack Encore + StimulusBundle
   get it auto-loaded via the `assets/package.json` advertisement;
   hosts without any JS pipeline simply keep the server-rendered
   behaviour.

If you use AssetMapper, remember EasyAdmin ignores the host importmap
by default — add it back via your Dashboard so the optional controller
boots on admin pages:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

final class DashboardController extends AbstractDashboardController
{
    public function configureAssets(): Assets
    {
        return Assets::new()->addAssetMapperEntry('app');
    }
}
```

### Saved views (POST routes)

`polysource/filter` ships a saved-views feature (dropdown, save,
load, delete). The bridge wires the create / delete routes for
EasyAdmin out of the box — they live at:

- `POST /admin/saved-views` (`polysource_saved_view_create`)
- `POST /admin/saved-views/{id}/delete` (`polysource_saved_view_delete`)

They are auto-imported — along with the 6 other `polysource_*`
routes — by `Bundle::boot()` since v0.5.4, so no `routes.yaml`
change is needed. Multi-tenant hosts mounting EA under a custom
prefix can opt out with `auto_register_routes: false` and import
`@PolysourceEasyAdminFilterBridge/Resources/config/routes.php`
under their own prefix instead.

A `BeforeCrudActionEvent` subscriber also expands `?view=<id>` into
the EA `filters[...]=...` query and redirects to a clean URL — no
host code needed.

## Quick start

Take any existing EasyAdmin CRUD controller:

```php
namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

final class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateTimeFilter::new('createdAt'))
            ->add(BooleanFilter::new('isActive'));
    }
}
```

After installing this bridge, **the same code** automatically:
- The `createdAt` filter renders with the enhanced datetime form type
  (dedicated block prefix for theme overrides) instead of the stock
  date picker.
- The `isActive` filter accepts an `include_null` option (default
  `false`) to show a third "Null" radio choice when the column is
  nullable.

To opt-in to per-resource overrides, pass `formTypeOptions` to the
upstream filter:

```php
->add(BooleanFilter::new('archivedAt')->setFormTypeOption('include_null', true))
```

## How the seam works

```
┌───────────────────────────────────────────────────────┐
│ EasyAdmin's FilterFactory::create()                   │
│                                                       │
│   foreach ($filterConfig as $filter) {                │
│     $filter = DateTimeFilter::new('createdAt')        │
│                  ->setFormType(DateTimeFilterType)    │  ← stock setup
│     $filterDto = $filter->getAsDto();                 │
│                                                       │
│     foreach ($this->filterConfigurators as $cfg) {    │  ← OUR HOOK
│       if (!$cfg->supports($filterDto, …)) continue;   │
│       $cfg->configure($filterDto, …);                 │  ← we mutate the DTO
│     }                                                 │
│   }                                                   │
└───────────────────────────────────────────────────────┘
```

**No EasyAdmin code is modified.** We only attach more services to
the existing extension point.

The full audit trail of seams used (and one that is *not* available —
the `EntityRepositoryInterface` returns `Doctrine\ORM\QueryBuilder`,
which blocks non-Doctrine sources) is in
[ADR-012 §Vérification technique](../../docs/adr/0012-dual-product-positioning.md#vérification-technique).

## Writing your own enhancer

Want to add a custom Configurator (e.g. a richer `TextFilter` with
mode-toggle "exact / starts-with / contains")? The pattern is small:

```php
namespace App\Filter\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\{EntityDto, FieldDto, FilterDto};
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;

final class TextFilterModeEnhancer implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return TextFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $filterDto->setFormType(MyTextFilterType::class);
        $filterDto->setFormTypeOptions(array_merge(
            $filterDto->getFormTypeOptions(),
            ['modes' => ['exact', 'starts_with', 'contains']],
        ));
    }
}
```

With Symfony's autowiring + autoconfiguration (default), it gets
auto-tagged `ea.filter_configurator`. **Done.** No service.yaml entry,
no compiler pass, no fork.

## Testing

```bash
# from the monorepo root
make test
```

Unit tests live in `tests/Unit/Configurator/` — they instantiate real
`FilterDto` instances (not mocks), run our `supports()` + `configure()`,
and assert the DTO mutations. `EntityDto` and `AdminContext` are `final`
in EasyAdmin v5, so the tests use
`(new ReflectionClass(...))->newInstanceWithoutConstructor()` to
satisfy the typehints without coupling to internal shape — the
Configurators never read either argument.

## Compatibility

Cf. [ADR-015 — multi-version baseline](../../docs/adr/0015-multi-version-compatibility-baseline.md);
CI runs the full matrix.

- **PHP** `>=8.1`
- **Symfony** `^5.4 || ^6.0 || ^7.0 || ^8.0`
- **EasyAdmin** `^4.24 || ^5.0`
- **Doctrine ORM** `^2.20 || ^3.0`

## Architectural decisions

- [ADR-012 — Dual-product positioning](../../docs/adr/0012-dual-product-positioning.md) —
  why this bridge exists alongside `polysource/admin` standalone.
- [ADR-016 — Bridge contracts shared with polysource/filter](../../docs/adr/0016-bridge-contracts-shared-with-polysource-filter.md) —
  the `ChipFormatterInterface` boundary between the bridge and the standalone primitive.

## License

MIT — see [LICENSE](../../LICENSE).
