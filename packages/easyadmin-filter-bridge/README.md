# polysource/easyadmin-filter-bridge

> Drop-in package that **enriches the filters of an existing EasyAdmin v5
> app** without forking EasyAdmin. Plugs into EasyAdmin's
> `FilterConfiguratorInterface` extension point.

## Status

**Pre-v0.1.0 — PoC stage.** The seam is technically validated (cf.
[ADR-012](../../docs/adr/0012-dual-product-positioning.md)). Two
enhancers shipped so far (`DateTime`, `Boolean`); the rest land in
[Phase 9.7](../../docs/roadmap/development-plan.md).

## What it does

Once installed, EasyAdmin's built-in filters gain richer form types,
**without any change to your existing CRUD controllers**:

| Built-in filter | Enhancement (today) | Enhancement (Phase 9.7) |
|---|---|---|
| `DateTimeFilter` | Presets option (`today`, `last_7_days`, `last_30_days`, `this_month`, `custom`), `show_clear` flag. | Date range UI, Twig theme with one-click preset buttons. |
| `BooleanFilter` | Optional `include_null` flag — adds a third "Empty / Null" choice to filter rows where the column is `NULL`. | Toggle-switch UI variant. |
| `TextFilter`, `NumericFilter`, `ChoiceFilter`, `EntityFilter`, `ComparisonFilter`, `ArrayFilter` | — | Coming in Phase 9.7. |

Plus, post-9.7:
- Filter **chips/tags** above the table (active filters visible, click X to remove).
- **Session persistence** of filters per CRUD controller FQCN.
- Custom filters: `BetweenDateFilter`, `InFilter`, `NotNullFilter`, `FullTextSearchFilter`.

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
  (presets + clear button) instead of the stock date picker.
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

- **PHP** 8.4+ (will widen to 8.2+ in v0.5+)
- **Symfony** 7.4 LTS (will widen to 6.4+ in v0.5+)
- **EasyAdmin** `^4.24 || ^5.0`

## Architectural decisions

- [ADR-012 — Dual-product positioning](../../docs/adr/0012-dual-product-positioning.md) —
  why this bridge exists alongside `polysource/admin` standalone.
- [ADR-009 — local agent context](../../docs/adr/0009-ai-assistant-context.md) —
  context system for ongoing development.

## License

MIT — see [LICENSE](../../LICENSE).
