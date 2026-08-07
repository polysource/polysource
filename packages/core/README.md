# polysource/core

> Core contracts and value objects for Polysource. Pure PHP 8.2+. **Zero Symfony dependency.**

The contract layer the rest of Polysource builds on — and the contract layer **your extensions implement**. 38 public types total. Coverage gate ≥ 90%.

## The contracts

Tiny on purpose — no God Object. [ADR-010](https://github.com/polysource/polysource/blob/main/docs/adr/0010-core-api-surface-criterion.md) is the criterion that governs how many public types this package is allowed to carry; every addition has to be argued there.

| Contract | Methods | What it abstracts |
|---|---|---|
| `DataSourceInterface` | **3** (`search`, `find`, `count`) | Any read-only data source — Doctrine, Redis, Meilisearch, an HTTP API, your microservice |
| `WritableDataSourceInterface` | extends + **3** (`create`, `update`, `delete`) | Adds write — UI auto-detects and shows write affordances |
| `ResourceInterface` | **8** | The whole resource config surface — name, label, identifier property, data source, fields, actions, filters, permission |
| `FieldInterface` | n/a (DTO + trait) | A column declaration |
| `TextField` / `IdField` / `BooleanField` / `DateTimeField` / `CodeField` | static `::new($property, $label)` | Built-in concrete field types backed by the twig-theme `field/*.html.twig` templates |
| `ActionInterface` | 5 | Base action contract (inline / bulk / global specialise) |
| `InlineActionInterface` | extends | Per-record action (button on each row) |
| `BulkActionInterface` | extends | Multi-record action (selection-driven) |
| `FilterInterface` | n/a (DTO) | A filter declaration |
| `PermissionInterface` | **1** (`isGranted`) | Plug your auth backend (Symfony default, OPA, LDAP, custom) |
| `AdminPluginInterface` | **2** (`getPluginName`, `getPluginVersion`) | Self-contained capability bundle (cf. [ADR-018](https://github.com/polysource/polysource/blob/main/docs/adr/0018-admin-plugin-interface-and-public-contracts.md)) |

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
- `RowDetail` — declarative payload for an expandable row-detail panel: `RowDetail::template($template, $context)` renders your own Twig, `RowDetail::listing($resourceName, $parentFilters, $pageSize)` embeds another Polysource resource
- 13 more — see `src/`.

## Install

```bash
composer require polysource/core
```

Requires PHP 8.2+ (cf. [ADR-015](https://github.com/polysource/polysource/blob/main/docs/adr/0015-multi-version-compatibility-baseline.md)).

## Why "zero Symfony dependency"

You can use `polysource/core` in any PHP framework — Laravel, Slim, vanilla, anywhere. The Symfony wiring lives in `polysource/symfony-bundle`. The contracts here travel.

This is the line we won't cross: any PR adding a Symfony dependency to this package gets rejected on principle. See [ADR-007 — PHP / Symfony version baseline](https://github.com/polysource/polysource/blob/main/docs/adr/0007-php-symfony-versions.md).

## Documentation

- [Architectural choices (ADRs)](https://github.com/polysource/polysource/tree/main/docs/adr/)
- [Extension points](https://github.com/polysource/polysource/blob/main/docs/user/extensibility.md) — every contract here, with sample code
- [Architecture cible](https://github.com/polysource/polysource/blob/main/docs/architecture/target-architecture.md) — full signatures + request flow

## License

MIT
