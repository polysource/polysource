# `filter-standalone-demo` — `polysource/filter` without an admin framework

Runnable Symfony app proving that `polysource/filter` works as a
**standalone primitive** — no EasyAdmin, no Sonata, no API Platform.
Just a controller, a Twig template, and a handful of filter
formatters auto-tagged via Symfony's `autoconfigure`.

This demo deliberately runs on the **floor** of Polysource's
multi-version baseline (cf.
[ADR-015](../../docs/adr/0015-multi-version-compatibility-baseline.md)):

- **PHP 8.1** (the lowest version the bundle advertises)
- **Symfony 5.4 LTS** (the lowest minor of the wider
  `^5.4 || ^6.0 || ^7.0 || ^8.0` constraint)
- No AssetMapper, no Stimulus Bundle (server-rendered chips)

A user on a legacy stack can clone this demo and verify the
primitive boots without upgrading Symfony or PHP first.

## Run it

```bash
make install
make serve
# open http://localhost:8082
```

The page lists 12 products with 4 filters:
- multi-select category (operator `in`)
- between price (numeric range)
- date "released after" (gte)
- boolean availability

Apply any combination — the URL updates, the chips bar appears
above the list, the rendered count adjusts. Click "Clear all" or
"Reset" to start over.

## What the demo wires up

Polysource side:
- `Polysource\Filter\PolysourceFilterBundle` — registered in
  `config/bundles.php`
- 4 `FilterFormatterInterface` services in `src/Filter/` (one per
  filter `name`) — auto-tagged `polysource.filter.formatter` via
  Symfony's `autoconfigure: true`
- `filter_tags()` Twig function — used in `templates/product/list.html.twig`
  to render the chips bar

Host side:
- `src/Entity/Product.php` — plain POPO, no Doctrine
- `src/Repository/InMemoryProductRepository.php` — 12 hard-coded
  fixtures
- `src/Filter/ProductFilters.php` — declarative `FilterDefinition`
  catalogue
- `src/Filter/ProductFilterApplier.php` — translates each
  `FilterCriterion` into a PHP `array_filter()` predicate
- `src/Controller/ProductController.php` — single GET route, builds
  the `FilterCollection` from URL query parameters

## What this demo does NOT show (yet)

- Session persistence (`FilterService::save() / load()`) — the demo
  reads filters from the URL only
- The 3 UI modes (`integrated`, `subpanel`) shipped with the
  bundle — those expect a Symfony Form + Stimulus, deferred to a
  future demo iteration

## Why this demo matters

Polysource's audience target is wider than EasyAdmin users. Anyone
running a Symfony admin (Sonata, API Platform back-office,
hand-rolled CRUD) can install `polysource/filter` and gain a
declarative filter API + chips bar without touching their existing
admin framework. This demo is the proof.
