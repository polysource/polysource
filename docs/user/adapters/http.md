# `polysource/adapter-http`

Read+write external REST API resources through the Polysource admin
— Stripe customers, Mailchimp lists, GitHub issues, internal
microservices, anything that speaks JSON over HTTP.

## Install

```bash
composer require polysource/adapter-http symfony/http-client
```

```php
// config/bundles.php
return [
    // …
    Polysource\Adapter\Http\PolysourceAdapterHttpBundle::class => ['all' => true],
];
```

## Wire a resource

```php
use Polysource\Adapter\Http\DataSource\HttpDataSource;
use Polysource\Adapter\Http\Pagination\PageNumberPaginationStrategy;
use Polysource\Adapter\Http\Resource\HttpResource;
use Polysource\Bundle\Attribute\AsResource;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsResource]
final class CustomerResource extends HttpResource
{
    public function __construct(HttpClientInterface $client)
    {
        parent::__construct(
            dataSource: new HttpDataSource(
                client: $client,
                baseUri: 'https://api.example.com/v1/customers',
                pagination: new PageNumberPaginationStrategy(),
                defaultHeaders: ['Authorization' => 'Bearer ' . getenv('CUSTOMERS_API_TOKEN')],
            ),
            slug: 'customers',
            label: 'Customers',
            permission: 'POLYSOURCE_CUSTOMER_VIEW',
        );
    }

    public function configureFilters(): iterable
    {
        return [];
    }
}
```

For multi-tenant setups (one client per upstream API), inject named
scoped clients via Symfony's `#[Target]` attribute — e.g.
`#[Target('stripe.client')] HttpClientInterface $stripe`.

## Pagination strategies

Two strategies ship out of the box; hosts implement
`PaginationStrategyInterface` for proprietary shapes:

### `PageNumberPaginationStrategy` (default)

Maps `Pagination::offset+limit` to `?page=N&per_page=M`. Default
response shape:

```json
{ "data": [...], "meta": { "total": 123 } }
```

Override `itemsKey` / `totalKey` for APIs with different conventions
(WordPress: `posts` array + `meta.x_wp_total`).

### `CursorPaginationStrategy`

Best for high-volume APIs (Stripe, Twitter, GitHub v4). Maps the
non-zero `Pagination::offset` to an opaque cursor token. Default
shape:

```json
{ "data": [...], "next_cursor": "abc..." }
```

Total is intentionally null per ADR-002 — cursor APIs don't expose
counts cheaply.

## What the data source does

- **`search()`** — `GET baseUri?<paginationParams>&<filters>&q=<text>`.
  Filters with operator `eq` become passthrough query params; other
  operators are skipped (hosts ship a custom data source for richer
  mapping).
- **`find($id)`** — `GET baseUri/{id}` — 404 returns `null`.
- **`count()`** — defers to whatever the strategy parses. Often
  null for cursor APIs, an int for page-based.
- **`create()`** — `POST baseUri` with the payload as JSON body.
- **`update()`** — `PATCH baseUri/{id}` with the payload as JSON
  body (PATCH is the safer default — full PUT can be wired by
  shipping a custom data source).
- **`delete()`** — `DELETE baseUri/{id}`. 404 is treated as success
  (idempotent — same convention as Doctrine / Flysystem / Redis
  adapters).

All HTTP errors during write operations bubble as
`RuntimeException` carrying the upstream status code + message.

## Auth

Pass `defaultHeaders` to the data source for static auth (Bearer
tokens, API keys). For per-request auth (signed requests, OAuth
refresh), wire a custom `HttpClientInterface` decorator that
injects the right headers — Symfony's `ScopingHttpClient` is the
canonical pattern.

## See also

- [ADR-002 — DataPage::total semantics (cursor pagination)](../../adr/0002-data-page-total-semantics.md)
- [`docs/user/cookbook/build-your-own-adapter.md`](../cookbook/build-your-own-adapter.md) — implementing a custom pagination strategy or a richer filter mapping.
