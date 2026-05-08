# `polysource/search` — global cross-resource search + Cmd+K palette

`polysource/search` ships a Linear/Notion-style command palette for
Polysource admins: hit `Cmd+K` (or `Ctrl+K`, or `/`), type a few
characters, navigate to any record across any resource — without
ever opening a navigation menu.

Per [ADR-023](../../adr/0023-global-search-cmdk.md), this is a
**separate opt-in package**. Apps that don't need search pay no
cost.

## What's in this folder

| File | What's in it |
|---|---|
| [installation.md](./installation.md) | Composer require, bundle registration, palette include in your layout, JSON endpoint smoke test. |
| [walkthrough.md](./walkthrough.md) | End-to-end UX walk + custom provider example. |

## Status

Pre-v0.1.0. Public API per ADR-023:
- `SearchResult` VO + `SearchProviderInterface` + `SearchAggregator`
- `ResourceSearchProvider` (default Resource→search bridge)
- `SearchController` (JSON endpoint at `GET /admin/search?q=…`)
- `SearchExtension` Twig (`polysource_search_palette()`)
- Stimulus `polysource--search--cmdk` controller (Cmd+K / Ctrl+K / `/`)

## Why this matters

EasyAdmin / Sonata / API Platform admin UIs ship per-CRUD search
inputs but no transversal palette. SREs / on-call / power users
end up bookmarking deep links because navigating menus is slow.
`polysource/search` is the only Symfony admin solution offering a
modern command palette out of the box.

Three contention layers protect the palette UX:
- per-provider limit (default 5)
- total wall-clock budget (default 250ms — slow provider gets cut)
- try/catch contention (one bad provider doesn't break the palette)

The default `ResourceSearchProvider` delegates to each
`DataSource::search()` so adapters that already know how to match
text (Doctrine LIKE, Meilisearch, Redis SCAN, …) work out of the
box. Future bridges (`polysource/search-meilisearch`,
`polysource/search-algolia`) ship as separate add-on packages
implementing `SearchProviderInterface`.

## See also

- [ADR-023 — Global search + Cmd+K](../../adr/0023-global-search-cmdk.md)
- [ADR-018 — AdminPluginInterface + public contracts](../../adr/0018-admin-plugin-interface-and-public-contracts.md)
