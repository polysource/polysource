# Polysource

> **Admin anything from Symfony — and bring your own everything.**
>
> A Symfony admin engine built on **14+ public extension points** (1 to 5 methods each). Plug in your data source, your search backend, your audit sink, your dashboard tiles, your permission backend, your filter formatter — without forking a single line. The 16 bundled packages are themselves implementations of these contracts; yours sit next to them as equal citizens.

**What you get out of the box** that goes beyond the usual Doctrine-CRUD admin:

- 🔌 **6 adapters** ready: Doctrine, Messenger, Redis, S3 / Flysystem, HTTP REST, Meilisearch — each ~80-300 LOC and a model for the next one you'll write
- 🔍 **Cmd+K cross-resource search palette** with a 3-method provider contract (Algolia / ES / your service in 30 min)
- 📊 **Dashboard widgets** (KPI counters, top-N lists, sparkline charts) composable in code
- 📝 **Saved views** — per-user / per-team / public, owner-aware voter, dropdown auto-wired
- ⚡ **Async bulk actions** over Messenger with live Mercure progress + cancel mid-flight (retry 5 000 failed messages without timing out)
- 🛡️ **GDPR Art. 30 / HIPAA audit trail** with a 1-method `AuditLoggerInterface` (pipe to Splunk, Datadog, your SIEM)
- 🔄 **Symfony Workflow integration** — auto-generated transition buttons + state chip per resource
- 🎯 **Field-level + action-level + resource-level permissions** through one `PermissionInterface` (Symfony default; swap for OPA / LDAP / custom)
- 🎨 **Enhanced filter UX** — date presets, range pickers, multi-select, between, session persistence, chips bar — usable standalone OR dropped into an existing EasyAdmin app (4.24+ or 5.0+) **without forking**

**Two products, same primitives, run side-by-side in the same app:**

1. **`polysource/easyadmin-filter-bridge`** — drop next to your existing EasyAdmin install (4.24+ or 5.0+), gain everything in the filter list above. Zero EA fork.
2. **Polysource standalone admin** (install via `polysource/symfony-bundle` plus the adapters you need) — admin for resources outside Doctrine ORM: Messenger failed messages, feature flags in Redis, files on S3, external REST APIs, Meilisearch indexes, configurations, jobs, webhooks.

→ **Full extensibility map**: [`docs/user/extensibility.md`](./docs/user/extensibility.md) — every contract, every method count, every registration tag, with sample code.

---

## Install

```bash
# Polysource standalone admin — start with the bundle + the adapter you need
composer require polysource/symfony-bundle polysource/adapter-messenger

# Or just the EasyAdmin filter bridge, dropped next to your existing EA install
composer require polysource/easyadmin-filter-bridge
```

Symfony Flex registers the bundles automatically. Full guide (manual registration, dev install from the monorepo, package picker) in [`docs/user/installation.md`](./docs/user/installation.md). Five-minute path to a working dashboard in [`docs/user/getting-started.md`](./docs/user/getting-started.md).

## Status — v0.9.1 published (2026-08-06)

16 packages distributed on Packagist as [`polysource/*`](https://packagist.org/?query=polysource%2F), mirrored from this monorepo via [an automated subtree-split pipeline (ADR-026)](./docs/adr/0026-monorepo-split-and-packagist-mirrors.md). The public API is **release-candidate stable** — committed for v0.9.x but not SemVer-frozen until v1.0. Breaking changes between minors are allowed, signalled in the [CHANGELOG](./CHANGELOG.md), and bounded by the freeze checklist in [ADR-011](./docs/adr/0011-pre-v1.0-freeze-checklist.md). License: MIT. See [ROADMAP.md](./ROADMAP.md) for the next minors and the [ADRs](./docs/adr/) for the choices that landed.

**v0.2.0 → v0.9.1 highlights** (since the v0.1.0 base — full per-version detail in [CHANGELOG.md](./CHANGELOG.md)):

- **v0.2.0** — Simplification: Stimulus controllers replaced by native `<details>` HTML, [ADR-027 progressive enhancement](./docs/adr/0027-progressive-enhancement.md) + [ADR-028 scope discipline](./docs/adr/0028-scope-discipline.md) ratified.
- **v0.3.0** — Column visibility toggle (per-user prefs), default saved view per user (`★`), row conditional styles, streaming CSV/XLSX export.
- **v0.4.0** — Filter-from-cell dropdown, per-column quick filter row, cross-page bulk selection + dry-run preview, empty-state design system.
- **v0.5.0** — Filter-aware export, frozen/sticky columns, row density toggle, toast notifications, column widths on saved views, column reordering, bulk action history audit log, keyboard shortcut cheat sheet, recently viewed records, short filter URL tokens.
- **v0.5.1 → v0.5.7** — Six dogfooding-driven patch releases hardening the install path (multi-kernel + multi-tenant safety), runtime (Doctrine ORM 2.x export streaming, IN/NULL filter DQL, PHP 8.4 deprecations, stale-schema degrade), and UX (horizontal filter tab strip, ISO 8601 date export). Zero breaking change.
- **v0.6.0** — `polysource:doctor` install/runtime diagnostics command, plus the bridge endpoints' integration-test kernel.
- **v0.7.x** — Pre-v1.0 freeze prep: `FilterOperator` enum replaces free-form operator strings, remaining [ADR-011](./docs/adr/0011-pre-v1.0-freeze-checklist.md) items closed (two breaking changes, both signalled).
- **v0.8.x** — Redis adapter reshaped into 5 type-pure data sources (string / list / hash / set / sorted-set), shared `FilterUrlBuilder` for the `filters[...]` URL shape.
- **v0.9.0** — Architectural cleanup: CSRF on all saved-view POST routes, open-redirect + XSS hardening, 17 of 22 audit items resolved ([tracker](./docs/maintainers/v0.9.0-architectural-cleanup.md)); the large refactors are scheduled for v0.10.
- **v0.9.1** — Maintenance: dependency-freeze thaw, per-package dist hygiene (explicit deps, export-ignores, derived plugin versions), Mercure bulk-async broadcaster registration fix.

Each helper is a Twig function or DI-wired service composable from the host's templates — never auto-injected into pages the host didn't opt into. See [`whats-new.md`](./docs/user/easyadmin-filter-bridge/whats-new.md) for the full feature index.

**Multi-version baseline (cf. [ADR-015](./docs/adr/0015-multi-version-compatibility-baseline.md))**: PHP 8.1 → 8.4, Symfony 5.4 / 6.4 / 7.2 / 7.4 LTS, EasyAdmin 4.24+ / 5.0+, Doctrine ORM 2.20+ / 3.6+. CI runs the full matrix.

## Packages

| Package | What it does |
|---|---|
| `polysource/core` | 26 public types, zero Symfony dep — pure PHP 8.1+ contracts |
| `polysource/filter` | Filter primitives (collection, criteria, session persistence, **saved views**) — usable standalone in any Symfony app |
| `polysource/easyadmin-filter-bridge` | Drop-in for EasyAdmin (4.24+ or 5.0+) — auto-swaps built-in filter form types, ships 4 custom filters (Between/In/NotNull/FullText), saved views dropdown |
| `polysource/symfony-bundle` | Symfony bundle wiring for the admin: DI extension, route loader, controllers, view listener, CSRF, pagination caps |
| `polysource/twig-theme` | Default Bootstrap 5 templates for index/detail/forms/fields |
| `polysource/adapter-messenger` | Browse + retry / dismiss / retry-all / purge Messenger failed envelopes |
| `polysource/adapter-doctrine` | Doctrine ORM cohabitation case (whitelist filter properties) |
| `polysource/adapter-redis` | Browse + write Redis hashes (Predis), SCAN cursor pagination |
| `polysource/adapter-flysystem` | Browse + write files on S3 / local / Azure / GCS via Flysystem |
| `polysource/adapter-http` | Admin external REST APIs via Symfony HttpClient (page-number + cursor strategies) |
| `polysource/adapter-meilisearch` | Browse + write Meilisearch indexes |
| `polysource/audit` | GDPR Art. 30 audit trail for admin actions (Doctrine storage, CSV export, retention purge) |
| `polysource/bulk-async` | Bulk actions over Messenger with live progress (Mercure) + cancel mid-flight |
| `polysource/widgets` | Dashboard widgets (KPI counters, top-N lists, sparkline charts) |
| `polysource/search` | Cross-resource search palette (Cmd+K) with fan-out aggregator |
| `polysource/workflow-bridge` | Symfony Workflow integration (auto transition buttons, state chip) |

## Run the demos

| Demo | Stack | Audience |
|---|---|---|
| `make demo-showcase` | PHP 8.4 + Sf 7.4 LTS + EA 5 + Doctrine 3 + Foundry 2 + Postgres 17 + Redis + Mercure + Meilisearch + S3 (MinIO) | **Hero demo** — every capability live, end-to-end |
| `make demo` | PHP 8.4 + Sf 7.4 + Doctrine | Messenger failed-messages dashboard |
| `make demo-bridge` | PHP 8.4 + Sf 7.4 + EA 5 | EasyAdmin v5 hosts adopting the bridge |
| `make demo-bridge-v4` | PHP 8.1 + Sf 6.4 LTS + EA 4.29 | EA v4 hosts (proves the floor of the multi-version baseline) |
| `make demo-filter` | Vanilla Sf 6.4 + PHP 8.1, **no EasyAdmin** | Sonata users, API Platform back-offices, hand-rolled admin |

Each demo is a self-contained Docker compose; first `make demo*` triggers a one-time build. See each demo's `README.md` for the per-demo walkthrough. EA demo login: `admin` / `admin`.

## Why Polysource

Symfony's admin landscape is mature for one shape of resource: a Doctrine ORM entity with a CRUD lifecycle. Anything else — a queue, a feature flag, a file on S3, an upstream REST API, a search index, a config file — sits outside that shape, and the admin engines that do it well are scarce.

Polysource starts from the other end. The contract a resource has to satisfy is **3 read methods + 3 write methods**, no Doctrine inheritance, no entity manager required. Once it satisfies that, every capability the framework ships — filters, saved views, audit log, bulk async, search palette, dashboard widgets, workflow integration — applies uniformly across **whatever** the resource is.

That's why the same engine handles your products table AND your Messenger failed transport AND your Redis feature-flags AND your Meilisearch index in one admin, with one auth model, one audit trail, one progress tracker.

## What it is **not**

- **Not a fork of EasyAdmin.** EA is excellent for Doctrine CRUD; we don't try to replace it. The bridge enriches it without forking.
- **Not a frontal competitor on Doctrine.** For pure Doctrine entities, keep using EasyAdmin. The `polysource/adapter-doctrine` exists for cohabitation, not replacement.
- **Not a no-code internal-tool builder** (use Retool / Appsmith for that).
- **Not a SaaS** — Polysource is a self-hosted Symfony bundle, MIT-licensed.
- **Not a BI dashboard** (use Grafana / Metabase).

## Architecture summary

```
polysource/
├── core/                       contracts + value objects, no Symfony deps
├── filter/                     filter primitives (standalone usable)
├── easyadmin-filter-bridge/    drop-in for EasyAdmin (4.24+ or 5.0+)
├── symfony-bundle/             admin wiring (DI, routing, controllers, Twig)
├── twig-theme/                 default UI templates
├── adapter-{messenger,doctrine,redis,flysystem,http,meilisearch}/
├── audit/                      GDPR / HIPAA action log
├── bulk-async/                 async bulk + Mercure progress
├── widgets/                    dashboard widgets
├── search/                     Cmd+K palette
└── workflow-bridge/            Symfony Workflow integration
```

Read the full design in [`docs/architecture/target-architecture.md`](./docs/architecture/target-architecture.md).

## Documentation

- [User documentation](./docs/user/) — installation, getting-started, per-package guides, cookbook, API reference
- [Showcase tour with screenshots](./docs/user/showcase-tour.md)
- [EasyAdmin filter bridge — getting started](./docs/user/easyadmin-filter-bridge/getting-started.md)
- [What's new vs upstream EasyAdmin (per-filter matrix)](./docs/user/easyadmin-filter-bridge/whats-new.md)
- [Cookbook — build your own adapter](./docs/user/cookbook/build-your-own-adapter.md)
- [Cookbook — add a custom action](./docs/user/cookbook/adding-a-custom-action.md)
- [Cookbook — permissions with roles](./docs/user/cookbook/permissions-with-roles.md)
- [Strategy / vision](./docs/strategy/product-vision.md)
- [Architecture decisions (ADR)](./docs/adr/) — 26 ADRs covering identifiers, routing, immutability, multi-version baseline (ADR-015), dual-product positioning (ADR-012), plugin architecture (ADR-018), showcase demo (ADR-025), monorepo split + Packagist mirrors (ADR-026), etc.
- [Roadmap](./ROADMAP.md) — what's planned for v0.10 and the road to v1.0
- [Changelog](./CHANGELOG.md) — versioned history of releases

## Bring your own everything — the contract reference

The opening pitch in one table. **Most contracts are 1-5 methods** — that's the design budget; past 5 we open an ADR (cf. [ADR-010](./docs/adr/0010-core-api-surface-criterion.md)).

| You want to… | Implement | Methods | Time |
|---|---|---|---|
| Admin a new data source (Stripe, your microservice, a queue) | `DataSourceInterface` | **3** read + 3 write | 1-2 hours |
| Plug a custom backend into Cmd+K search | `SearchProviderInterface` | **3** | 30 min |
| Pipe audit log to Splunk / Datadog / a SIEM | `AuditLoggerInterface` | **1** | 15 min |
| Render a custom dashboard tile | `WidgetInterface` | **5** | 1 hour |
| Persist saved views in Redis instead of Doctrine | `SavedViewStorageInterface` | **5** | 1 hour |
| Replace the permission backend (LDAP / OPA / custom voter) | `PermissionInterface` | **1** | 15 min |
| Custom HTTP pagination (Link headers, RFC 5988…) | `PaginationStrategyInterface` | **2** | 30 min |
| Format a filter chip your way | `ChipFormatterInterface` | **1** | 10 min |
| Ship a self-contained capability bundle | `AdminPluginInterface` + `#[AsPlugin]` | **3 metadata** | 1 hour |

**No global registries, no XML, no magic.** Every extension is a Symfony service tag scanned by `tagged_iterator(...)`. Discoverable by reading `services.php`.

→ **Full extensibility map**: [`docs/user/extensibility.md`](./docs/user/extensibility.md) — 14 extension points with sample code, registration pattern, and the anti-patterns we explicitly resist.

→ **Cookbook**: [build your own adapter](./docs/user/cookbook/build-your-own-adapter.md) — patterns we learned shipping the 6 bundled adapters.

## Quality bar

- **782 unit + functional tests / 1932 assertions** in the package matrix
- **29 browser E2E tests** (Symfony Panther) + **15 adapter real-container tests** (Redis / Meilisearch / MinIO / WireMock) running in CI on every push
- **PHPStan level max** across all packages
- **PHP-CS-Fixer** PSR-12 + Symfony rules
- **Core coverage gate ≥ 90%** (`polysource/core` sits at 99.17%)
- **CI matrix** runs PHP 8.1/8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0

## Known limitations pre-v1.0

Polysource is published and dogfooded against real client integrations, but not yet SemVer-frozen. Adopt early, with eyes open:

- **Some UI surfaces are intentionally minimal** (drag-drop dashboard composition, fluent form builders, real-time collaborative cursors are out of scope pre-v1.0 — cf. [ADR-028](./docs/adr/0028-scope-discipline.md)).
- **The standalone admin is not a replacement for EasyAdmin on pure Doctrine CRUD.** Keep using EasyAdmin for that. The Polysource standalone admin shines on resources outside Doctrine (Messenger, Redis, S3, REST, Meilisearch).
- **Concrete field helpers are still limited.** The bundled `Field` API covers common types (text, datetime, money, boolean, code) but advanced renderers (rich-text editors, dependent dropdowns, file uploaders with progress) are app-side for now.
- **Some advanced capabilities are opt-in packages and require host wiring.** Audit log, bulk-async, widgets, search palette, workflow bridge each ship as a separate Composer package — install and register only what you use.
- **The public API is release-candidate stable but not SemVer-frozen until v1.0.** Breaking changes between minors are allowed, signalled in the CHANGELOG, and bounded by [ADR-011](./docs/adr/0011-pre-v1.0-freeze-checklist.md).
- **Cursor pagination is best-effort on adapters that don't expose a total count** (Redis SCAN, some HTTP APIs, Meilisearch in some configurations). The UI gracefully degrades to "Previous / Next" without page-of-N counts.
- **Browser-driven UX (Cmd+K palette, filter modal, Mercure SSE)** is exercised by the Panther suite against Chromium 128. Older browsers / Safari quirks are not yet pinned in CI.

## Contributing

- Read [`CONTRIBUTING.md`](./CONTRIBUTING.md) for the development workflow.
- Strict scope per [ADR-012](./docs/adr/0012-dual-product-positioning.md) — feature requests outside the dual-product positioning are politely declined.
- See [`docs/strategy/product-vision.md`](./docs/strategy/product-vision.md) §2 before opening a feature request.

## License

[MIT](./LICENSE)
