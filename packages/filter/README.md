# polysource/filter

> Filter primitives for Symfony admin UIs — usable standalone in any Symfony app, also the foundation of `polysource/easyadmin-filter-bridge` and `polysource/symfony-bundle`.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`FilterCollection`** + **`FilterCriterion`** — immutable value objects representing the active filter state, scoped by a stable `id` (typically the resource FQCN).
- **`FilterService`** — session-backed persistence (load / save / clear per resource).
- **`FilterCollectionType`** form type + `FilterHydrator` — bind a whole filter collection to a Symfony form and hydrate it back into criteria. (The *enhanced* EasyAdmin form types — date presets, between, in, full-text — live in `polysource/easyadmin-filter-bridge`, not here.)
- **The mapping pipeline** — `FilterMapperInterface` / `FilterFormatterInterface` / `FilterRendererInterface` plus their registries and a default triplet per filter kind (text, numeric, boolean, choice, datetime, entity, array-list). This is the URL → criteria → form → chip road, and every leg of it is swappable.
- **Twig extension `filter_tags`** — renders the active-filters chips bar.
- **Saved views** — `SavedView` VO + `SavedViewService` + Doctrine + in-memory storage adapters + Symfony voter for scope-aware visibility (private / team / public). See [ADR-019](https://github.com/polysource/polysource/blob/main/docs/adr/0019-saved-views-architecture.md).
- **`SavedViewExtension` Twig extension** — renders the dropdown.
- **Column preferences** — `ColumnPreferenceService` + Doctrine / in-memory storage + Twig extension: per-user column visibility and ordering.
- **Filter URL tokens** — `FilterUrlTokenService` + storage, turning a long filter query into a short shareable link, with a `polysource:filter-url-tokens:purge` command for retention.
- **Recent records** — `RecentRecordsService` + storage, the "recently viewed" trail per user.
- **Bulk action history** — `BulkActionHistoryService` + storage + `polysource:bulk-action-history:purge`, an append-only log of bulk runs.
- **Two Stimulus controllers** under `assets/controllers/`, advertised through `assets/package.json` so AssetMapper and Encore + StimulusBundle pick them up automatically:
  - `polysource--filter-chips` — chips-bar interactions.
  - `polysource--row-details` — the expandable row-details panel. It ships from *this* package and is shared by both the EasyAdmin bridge and the native `polysource/symfony-bundle` listing.

  Both are progressive enhancement only: without a JS pipeline the server-rendered behaviour stands (per [ADR-027](https://github.com/polysource/polysource/blob/main/docs/adr/0027-progressive-enhancement.md)).

## Audience

Standalone usage targets:
- Sonata users wanting better filter UX
- API Platform back-offices
- Hand-rolled admin DIY
- Any Symfony app that builds filter forms manually

For EasyAdmin v5 hosts, install [`polysource/easyadmin-filter-bridge`](https://github.com/polysource/polysource/tree/main/packages/easyadmin-filter-bridge/) instead — it wraps this package with auto-discovery.

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

## Extend it

| Need | Implement |
|---|---|
| Persist saved views in Redis / Mongo / your HTTP service | `SavedViewStorageInterface` (4 methods: `save` / `find` / `listVisible` / `delete`) |
| Resolve which "team" a user belongs to (for shared views) | `SavedViewTeamResolverInterface` (1 method) |
| Format a chip your way ("3 statuses" instead of "paid, shipped, …") | `ChipFormatterInterface` (1 method, ADR-016) |
| Take over the URL → criteria → form pipeline | `FilterMapperInterface` / `FilterFormatterInterface` / `FilterRendererInterface` |

See the [full extensibility map](https://github.com/polysource/polysource/blob/main/docs/user/extensibility.md).

## Documentation

- [Filter walkthrough](https://github.com/polysource/polysource/tree/main/docs/user/filter/)
- [Saved views walkthrough](https://github.com/polysource/polysource/blob/main/docs/user/filter/saved-views.md)
- [Standalone demo](https://github.com/polysource/polysource/tree/main/examples/filter-standalone-demo/)
