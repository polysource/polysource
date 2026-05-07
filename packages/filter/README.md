# polysource/filter

> Filter primitives for Symfony admin UIs — usable standalone in any Symfony app, also the foundation of `polysource/easyadmin-filter-bridge` and `polysource/admin`.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`FilterCollection`** + **`FilterCriterion`** — immutable value objects representing the active filter state, scoped by a stable `id` (typically the resource FQCN).
- **`FilterService`** — session-backed persistence (load / save / clear per resource).
- **Enhanced form types** — date presets (this-week / last-30-days / etc.), range pickers, multi-select, between, in.
- **Twig extension `filter_tags`** — renders the active-filters chips bar.
- **Saved views** — `SavedView` VO + `SavedViewService` + Doctrine + in-memory storage adapters + Symfony voter for scope-aware visibility (private / team / public). See [ADR-019](../../docs/adr/0019-saved-views-architecture.md).
- **`SavedViewExtension` Twig extension** — renders the dropdown.

## Audience

Standalone usage targets:
- Sonata users wanting better filter UX
- API Platform back-offices
- Hand-rolled admin DIY
- Any Symfony app that builds filter forms manually

For EasyAdmin v5 hosts, install [`polysource/easyadmin-filter-bridge`](../easyadmin-filter-bridge/) instead — it wraps this package with auto-discovery.

## Install

```bash
composer require polysource/filter
```

Register the bundle in `config/bundles.php`:

```php
return [
    Polysource\Filter\PolysourceFilterBundle::class => ['all' => true],
];
```

## Documentation

- [Filter walkthrough](../../docs/user/filter/)
- [Saved views walkthrough](../../docs/user/filter/saved-views.md)
- [Standalone demo](../../examples/filter-standalone-demo/)
