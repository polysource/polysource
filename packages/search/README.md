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

## Extend it

`SearchProviderInterface` is **3 methods** (`getId` / `getLabel` / `search`). To plug Algolia, Elasticsearch, your custom service into the Cmd+K palette:

```php
#[AutoconfigureTag('polysource.search.provider')]
final class AlgoliaSearchProvider implements SearchProviderInterface
{
    public function getId(): string { return 'algolia:products'; }
    public function getLabel(): string { return 'Products (Algolia)'; }
    public function search(string $query, int $limit, float $deadline): array
    {
        // Respect the deadline; the aggregator enforces a 250ms global budget.
    }
}
```

Done. The aggregator fan-outs across every tagged provider. See [extensibility map](../../docs/user/extensibility.md#2-plug-into-the-cmdk-search-palette).

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
