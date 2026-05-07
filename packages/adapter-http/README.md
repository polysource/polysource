# polysource/adapter-http

> HTTP REST API adapter for Polysource — admin Stripe, GitHub, internal microservices, any external REST API you operate but don't own the schema of.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`HttpDataSource`** — implements `WritableDataSourceInterface` over Symfony `HttpClientInterface`.
- **`PaginationStrategyInterface`** — pluggable pagination protocol with two built-in implementations:
  - `PageNumberPaginationStrategy` — `?page=N` style (Stripe-like)
  - `CursorPaginationStrategy` — opaque cursor in response (GitHub-like)
- `defaultHeaders` constructor arg for injecting auth headers (Bearer tokens, API keys).
- **`HttpResource`** — non-final convenience base.
- Tested with Symfony's `MockHttpClient` so no live API calls in CI.

## Install

```bash
composer require polysource/adapter-http symfony/http-client
```

Register the bundle:

```php
return [
    Polysource\Adapter\Http\PolysourceAdapterHttpBundle::class => ['all' => true],
];
```

## Extend it

For an API that paginates in an unusual way (link headers, X-Pagination, RFC 5988…), implement `PaginationStrategyInterface` (2 methods):

```php
final class LinkHeaderPaginationStrategy implements PaginationStrategyInterface
{
    public function buildRequest(DataQuery $query): array { /* return query + headers */ }
    public function parseResponse(ResponseInterface $response): DataPage { /* parse Link header */ }
}
```

Inject into `HttpDataSource`. No fork needed. See [extensibility map](../../docs/user/extensibility.md#6-custom-http-pagination-strategy).

## Documentation

- [Adapter HTTP guide](../../docs/user/adapters/http.md)
