# polysource/core

> Core contracts and value objects for Polysource. Pure PHP 8.1+. **Zero Symfony dependency.**

The contract layer the rest of Polysource builds on — and the contract layer **your extensions implement**. 26 public types total. Coverage gate ≥ 90% (currently 99.17%).

## The contracts

Tiny on purpose. If a contract grows past 5 methods we open an ADR (cf. [ADR-010](../../docs/adr/0010-core-api-surface-criterion.md)).

| Contract | Methods | What it abstracts |
|---|---|---|
| `DataSourceInterface` | **3** (`search`, `find`, `count`) | Any read-only data source — Doctrine, Redis, Meilisearch, an HTTP API, your microservice |
| `WritableDataSourceInterface` | extends + **3** (`create`, `update`, `delete`) | Adds write — UI auto-detects and shows write affordances |
| `BatchableDataSourceInterface` | extends + **1** (`findMany`) | Avoids N+1 across resources |
| `ResourceInterface` | 5 | What a Polysource Resource is — name, label, fields, actions, data source |
| `FieldInterface` | n/a (DTO + trait) | A column declaration |
| `ActionInterface` | 4 | Base action contract (inline / bulk / global specialise) |
| `InlineActionInterface` | extends | Per-record action (button on each row) |
| `BulkActionInterface` | extends | Multi-record action (selection-driven) |
| `FilterInterface` | n/a (DTO) | A filter declaration |
| `PermissionInterface` | **1** (`isGranted`) | Plug your auth backend (Symfony default, OPA, LDAP, custom) |
| `AdminPluginInterface` | 3 metadata | Self-contained capability bundle (cf. [ADR-018](../../docs/adr/0018-admin-plugin-interface-and-public-contracts.md)) |

## Value objects

All `final` with `readonly` properties. Mutations return new instances via `with*()` methods. Named exception: `FieldTrait` is a fluent mutable builder — never share a single field instance between two resources.

- `DataQuery` — filters + sort + pagination + search text
- `DataPage` — `list<DataRecord>` + optional `total` (`null` = unknown / cursor-based)
- `DataRecord` — opaque id + payload
- `DataPayload` — write-time payload
- `FilterCriterion` — `(property, operator, values)`
- `Pagination` — `(offset, limit)`
- `SortDirection` — enum
- `ActionResult` — `(outcome, message, data)` — `success` / `failure` / `exception`
- `AdminContext` — request-scoped admin state
- 17 more — see `src/`.

## Install

```bash
composer require polysource/core
```

Requires PHP 8.1+ (cf. [ADR-015](../../docs/adr/0015-multi-version-compatibility-baseline.md)).

## Why "zero Symfony dependency"

You can use `polysource/core` in any PHP framework — Laravel, Slim, vanilla, anywhere. The Symfony wiring lives in `polysource/symfony-bundle`. The contracts here travel.

This is the line we won't cross: any PR adding a Symfony dependency to this package gets rejected on principle. See `the project context file` §architectural constraints.

## Documentation

- [Architectural choices (ADRs)](../../docs/adr/)
- [Extension points](../../docs/user/extensibility.md) — every contract here, with sample code
- [Architecture cible](../../docs/architecture/target-architecture.md) — full signatures + request flow

## License

MIT
