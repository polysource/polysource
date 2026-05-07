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

## Documentation

- [Adapter HTTP guide](../../docs/user/adapters/http.md)
