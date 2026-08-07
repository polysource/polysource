# polysource/symfony-bundle

Symfony bundle that wires [polysource/core](https://github.com/polysource/polysource/tree/main/packages/core/) contracts into Symfony
6.4 LTS, 7.x and 8.x (DI, routing, controllers, Twig view layer).

This package is the entry point for any Symfony application using Polysource Admin.
The pure-PHP `core` package can be used standalone, but most users will install
this bundle to get full HTTP/Twig integration.

## Installation

```bash
composer require polysource/symfony-bundle
```

The bundle auto-registers itself via Symfony Flex.

## Configuration

```yaml
# config/packages/polysource.yaml
polysource:
    url_prefix: /admin   # default
```

## What it ships

**Routing.** `PolysourceRouteLoader` generates five physical routes for
every registered resource (cf. ADR-003), under the configured `url_prefix`:

| Method | Path | Controller |
|---|---|---|
| `GET` | `{prefix}/{slug}` | `IndexController` |
| `GET` | `{prefix}/{slug}/{id}` | `DetailController` |
| `GET` | `{prefix}/{slug}/{id}/detail-panel` | `RowDetailPanelController` |
| `POST` | `{prefix}/{slug}/batch/{action}` | `ActionController::bulk` |
| `POST` | `{prefix}/{slug}/{id}/{action}` | `ActionController` |

**Expandable row details.** Any resource may opt in by implementing
`HasRowDetailsInterface` (3 methods — `hasRowDetail(DataRecord)`,
`getRowDetail(DataRecord): ?RowDetail`, `getRowDetailPermission(): ?string`).
The `detail-panel` route serves the panel lazily as a fragment, and the
same URL renders a layout-wrapped standalone page when JavaScript is
absent (ADR-027). `RowDetail::listing()` embeds another Polysource
resource read-only, paged through `rd_page` by `EmbeddedListingRenderer`.

**Per-record action gating.** `ControllerSupport::collectRecordActionViews()`
evaluates inline actions against the row, not just the resource: the
permission check receives the `DataRecord` as voter subject, and
`isDisplayed()` receives a populated context — `record`, `subject` (the
domain object behind the record, e.g. the Doctrine entity), and `page`
(`'index'` or `'detail'`). An unknown record id answers 404, not 500.

**Plugins.** `PluginCompilerPass` + `PluginRegistry` collect
`AdminPluginInterface` implementations declared with `#[AsPlugin]`;
`polysource:plugins:list` dumps what the container found.

**`polysource:doctor`.** A console health check over the wiring most
likely to be wrong: PHP version, bundle registration, Doctrine schema,
plugin declarations, and EasyAdmin co-loading.

## Status

**Shipped — v1.1.0 (2026-08-07).** Public API frozen under strict SemVer
since v1.0.0 (2026-08-06): breaking changes only in a new major. See
[ROADMAP](https://github.com/polysource/polysource/blob/main/ROADMAP.md).

## Architectural decisions

This package implements:

- [ADR-003](https://github.com/polysource/polysource/blob/main/docs/adr/0003-routing-strategy.md) — physical routes per resource
- [ADR-004](https://github.com/polysource/polysource/blob/main/docs/adr/0004-admin-context-immutability.md) — `final readonly AdminContext`
- [ADR-005](https://github.com/polysource/polysource/blob/main/docs/adr/0005-configuration-mechanism.md) — `#[AsResource]` attribute + interface methods
- [ADR-011](https://github.com/polysource/polysource/blob/main/docs/adr/0011-pre-v1.0-freeze-checklist.md) — the pre-v1.0 API freeze checklist, which set the v1.0 floors (PHP 8.2+, Symfony 6.4 LTS+)
- [ADR-015](https://github.com/polysource/polysource/blob/main/docs/adr/0015-multi-version-compatibility-baseline.md) — multi-version compatibility baseline
- [ADR-018](https://github.com/polysource/polysource/blob/main/docs/adr/0018-admin-plugin-interface-and-public-contracts.md) — `AdminPluginInterface` + public contracts
- [ADR-027](https://github.com/polysource/polysource/blob/main/docs/adr/0027-progressive-enhancement.md) — server-side fallback for every interactive feature
- [ADR-033](https://github.com/polysource/polysource/blob/main/docs/adr/0033-expandable-row-details.md) — expandable row details

## License

MIT
