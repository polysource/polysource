# polysource/adapter-meilisearch

> Meilisearch adapter for Polysource — browse, search, manually correct documents in Meilisearch indexes.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`MeilisearchIndexInterface`** (4 methods) — minimal abstraction over the Meilisearch client.
- **`MeilisearchPhpIndexClient`** — production implementation against `meilisearch/meilisearch-php`.
- **`MeilisearchDataSource`** — search-first design (Meilisearch is a search engine, not a CRUD store).
- **Filter property sanitisation** — anti-injection via whitelist.
- **`MeilisearchIndexResource`** — non-final convenience base.

## Install

```bash
composer require polysource/adapter-meilisearch meilisearch/meilisearch-php
```

Register the bundle:

```php
return [
    Polysource\Adapter\Meilisearch\PolysourceAdapterMeilisearchBundle::class => ['all' => true],
];
```

## Documentation

- [Adapter meilisearch guide](https://github.com/polysource/polysource/blob/main/docs/user/adapters/meilisearch.md)
