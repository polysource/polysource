# Polysource — EasyAdmin filter bridge demo

A runnable Symfony 7.4 application that ships an EasyAdmin v5
dashboard with [`polysource/easyadmin-filter-bridge`](../../packages/easyadmin-filter-bridge)
installed. Every one of the **8 enhanced filter types** is wired on a
sample `Product` entity so you can see the bridge in action.

## Status

Fully working: Docker image, composer install, Doctrine schema + seed,
dashboard at `/admin/`, and the CRUD listing pages (`/admin/product`,
`/admin/category`) render the enhanced filters end-to-end — filter
modal with tabs + group accordions, chips bar, column-visibility
dropdown.

(The v0.1-era "known issue" — an upstream EasyAdmin `ButtonVariant`
enum rendered without `->value` — was fixed upstream and no longer
reproduces on the EA 5.0.7+ this demo locks. Verified 2026-08-06.)

## Five-minute walkthrough

```bash
make demo-bridge
```

(Run from the repo root — wraps `make -C examples/easyadmin-bridge-demo up`.)

The first run:

1. Builds the PHP 8.4 + SQLite container image (~2 min).
2. Installs Composer dependencies (~30 s).
3. Creates the SQLite database via `doctrine:schema:create`.
4. Seeds 5 categories and 30 products with varied dates, statuses,
   tags and prices.
5. Starts `php -S 0.0.0.0:8081`.

Open <http://localhost:8081/admin/> and log in:

| Field | Value |
|---|---|
| Username | `admin` |
| Password | `admin` |

You'll see the **Products** screen with a **Filters** button at the
top right. Open it and you'll see every enhanced filter:

| Property | Filter type | Enhancement opt-in (in `ProductCrudController::configureFilters`) |
|---|---|---|
| `name` | TextFilter | `min_length: 2` |
| `price` | NumericFilter | `step: 0.01` + 3 quick ranges (`< 50€` / `50–200€` / `> 200€`) |
| `stock` | ComparisonFilter | `comparisons: ['=', '>=', '<=']` |
| `isActive` | BooleanFilter | `include_null: true` (3rd "Null" radio) |
| `createdAt` | DateTimeFilter | `show_clear: true` + 5 default presets |
| `archivedAt` | DateTimeFilter | same as above |
| `status` | ChoiceFilter | `inline: true` (pills) |
| `tags` | ArrayFilter | `chip_display: true` |
| `category` | EntityFilter | `placeholder: 'Pick a category…'` |

Stop with `Ctrl+C`, then `make down` to remove the container.

## What's the install actually doing?

- The bridge bundle is auto-registered via `config/bundles.php`.
- Its DI extension implements `PrependExtensionInterface`, which
  injects the form theme `@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig`
  into `twig.form_themes` — host app needs no Twig config.
- Each enhancer service (`DateTimeFilterEnhancer`, …) is registered
  with `setAutoconfigured(true)`, so EasyAdmin's
  `registerForAutoconfiguration(FilterConfiguratorInterface::class)`
  applies the `ea.filter_configurator` tag at compile time.
- `FilterFactory::create()` then iterates the tagged Configurators
  on every filter creation — when one matches the FQCN, our enhanced
  formType replaces the upstream one.
- `FilterSessionPersistenceSubscriber` listens on
  `BeforeCrudActionEvent` and snapshots / restores filter values per
  CRUD controller FQCN — walk away from the page, come back, your
  filters are still there.

## Resetting

```bash
make clean    # wipes vendor/ and var/data.db
make demo-bridge
```

## License

MIT (same as the rest of the repository).
