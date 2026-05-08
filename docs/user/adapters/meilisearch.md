# `polysource/adapter-meilisearch`

Browse, search, facet, write Meilisearch indexes through the
Polysource admin. Best-fit case: indexes you maintain for end-user
search, that operators occasionally need to inspect, manually
correct, or browse for QA.

## Install

```bash
composer require polysource/adapter-meilisearch meilisearch/meilisearch-php
```

```php
// config/bundles.php
return [
    // …
    Polysource\Adapter\Meilisearch\PolysourceAdapterMeilisearchBundle::class => ['all' => true],
];
```

## Wire a resource

```php
use Meilisearch\Client;
use Polysource\Adapter\Meilisearch\Client\MeilisearchPhpIndexClient;
use Polysource\Adapter\Meilisearch\DataSource\MeilisearchDataSource;
use Polysource\Adapter\Meilisearch\Resource\MeilisearchIndexResource;
use Polysource\Bundle\Attribute\AsResource;

#[AsResource]
final class ProductIndexResource extends MeilisearchIndexResource
{
    public function __construct(Client $meilisearch)
    {
        $client = new MeilisearchPhpIndexClient($meilisearch->index('products'));

        parent::__construct(
            dataSource: new MeilisearchDataSource(
                index: $client,
                primaryKey: 'id',
            ),
            slug: 'product-index',
            label: 'Product index',
            permission: 'POLYSOURCE_PRODUCT_INDEX_VIEW',
        );
    }

    public function configureFilters(): iterable
    {
        return [];
    }
}
```

## What the data source does

- **`search()`** — `index.search($query, $options)`. Maps Polysource
  filters to Meilisearch's `filter` expression syntax (`field = 'x'`,
  `field IN [a, b]`, range operators), maps sort to
  `field:asc/desc`, pagination via `limit`/`offset`. Search-first by
  default — `DataQuery::$searchText` becomes the primary query.
- **`find($id)`** — `index.getDocument($id)`.
- **`count()`** — defers to `estimatedTotalHits` (Meilisearch's
  fast approximate count — good enough for UI footers).
- **`create()`** — refuses if the payload doesn't carry the primary
  key, then calls `addDocuments` (upsert).
- **`update()`** — forces the primary key from the URL identifier,
  then calls `addDocuments` (upsert — `update == create` in
  Meilisearch's model).
- **`delete()`** — `deleteDocument` (idempotent).

## Filter operators

Maps `eq`, `neq`, `gt/gte/lt/lte`, `in` to Meilisearch's filter
syntax. Filter property names are sanitised (alnum + dot +
underscore) to prevent expression injection through user-supplied
filter forms.

The host's index must declare any filterable property as a
`filterableAttribute` in the index settings; otherwise Meilisearch
rejects the search request. We deliberately don't shadow that
contract — server-side enforcement is the source of truth.

## When to use

- **Yes**: operators need to inspect what the index actually contains
  for QA, manually fix a stale document, audit indexed PII for a
  GDPR request.
- **Maybe**: simple browsing of a small index (< 10k documents) —
  you could also use the source-of-truth Doctrine entity instead.
- **No**: high-volume admin write traffic (>1 op/sec sustained) —
  Meilisearch's primary-key constraints + reindex latency aren't
  designed for it; treat the index as derived data and write to the
  source of truth.

## See also

- [ADR-002 — Pagination cursor](../../adr/0002-data-page-total-semantics.md)
- [`docs/user/search/`](../search/) — `polysource/search` (separate package — global Cmd+K palette across resources, **not** the same thing as this adapter).
- [`docs/user/cookbook/build-your-own-adapter.md`](../cookbook/build-your-own-adapter.md)
