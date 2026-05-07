# polysource/search

> Cross-resource search palette (Cmd+K / Ctrl+K / "/") for Polysource — Linear / Notion-style fastest-path navigation.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`SearchResult`** VO + **`SearchProviderInterface`** (3 methods: id / label / search with deadline contract).
- **`SearchAggregator`** — fan-out across tagged providers with 3 contention layers:
  1. per-provider limit
  2. total budget 250 ms
  3. try/catch isolation per provider
- **`ResourceSearchProvider`** — default impl wrapping any Polysource Resource via `DataSource::search()`.
- **`SearchController`** — JSON endpoint `GET /admin/search?q=…`.
- **`SearchExtension`** Twig (`polysource_search_palette()`).
- Stimulus **`cmdk_controller.js`** — Cmd+K / Ctrl+K / "/" hooks, debounce 150 ms, arrow-keys + Enter nav, Esc close, results grouped per resource.
- Accessible overlay template `_palette.html.twig`.

See [ADR-023](../../docs/adr/0023-global-search-palette.md). Future bridges (`search-meilisearch`, `search-algolia`, `search-elasticsearch`) extend via `SearchProviderInterface`.

## Install

```bash
composer require polysource/search
```

Register the bundle:

```php
return [
    Polysource\Search\PolysourceSearchBundle::class => ['all' => true],
];
```

## Documentation

- [Search walkthrough](../../docs/user/search/)
