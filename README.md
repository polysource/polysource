# Polysource

> Two complementary tools for Symfony admin panels — sharing the same primitives, usable together or alone.

1. **`polysource/easyadmin-filter-bridge`** — a drop-in package that **enriches the filters of an existing EasyAdmin v5 app** (date presets, range pickers, multi-select, between, session persistence, chips bar, saved views) **without forking EasyAdmin**.
2. **`polysource/admin`** — a standalone admin toolkit for resources that **don't live in a Doctrine ORM database**: Messenger failed messages, feature flags in Redis, files on S3, external REST APIs, Meilisearch documents, configurations, jobs, webhooks.

Both products share the same underlying primitives (`polysource/core` + `polysource/filter`) and can run side-by-side in the same Symfony app.

---

## Status — pre-v0.1.0

Phases 1 → 22 shipped on `main` (16 packages). The public API is stable and frozen for v0.1.0; tag and Packagist publish pending the final QA pass on the showcase. License: MIT. See [`docs/roadmap/development-plan.md`](./docs/roadmap/development-plan.md) for phase status, and the [ADRs](./docs/adr/) for the choices that landed.

**Multi-version baseline (cf. [ADR-015](./docs/adr/0015-multi-version-compatibility-baseline.md))**: PHP 8.1 → 8.4, Symfony 5.4 / 6.4 / 7.2 / 7.4 LTS, EasyAdmin 4.24+ / 5.0+, Doctrine ORM 2.20+ / 3.6+. CI runs the full matrix.

## Packages

| Package | What it does |
|---|---|
| `polysource/core` | 26 public types, zero Symfony dep — pure PHP 8.1+ contracts |
| `polysource/filter` | Filter primitives (collection, criteria, session persistence, **saved views**) — usable standalone in any Symfony app |
| `polysource/easyadmin-filter-bridge` | Drop-in for EasyAdmin v5 — auto-swaps built-in filter form types, ships 4 custom filters (Between/In/NotNull/FullText), saved views dropdown |
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

Most Symfony admin engines (EasyAdmin, Sonata, etc.) are designed around Doctrine ORM entities. They're excellent for that — but they don't naturally cover:

- **Messenger failed messages** (no built-in UI to retry/dismiss/purge)
- **Feature flags** stored in Redis or a custom store
- **Files** on local filesystem, S3, Azure, GCS
- **External REST APIs** that you operate but don't own the schema of
- **Meilisearch / Elasticsearch documents**
- **Background jobs**, **webhooks**, **YAML/JSON configuration**

Polysource was built for those cases — and along the way, the `polysource/easyadmin-filter-bridge` was carved out to fix the parts of EasyAdmin's filter UX that bother Doctrine users too.

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
├── easyadmin-filter-bridge/    drop-in for EasyAdmin v5
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
- [Cookbook — integrate into an existing app](./docs/user/cookbook/integrate-existing-app.md)
- [Cookbook — build your own adapter](./docs/user/cookbook/build-your-own-adapter.md)
- [Strategy / vision](./docs/strategy/product-vision.md)
- [Architecture decisions (ADR)](./docs/adr/) — 25 ADRs covering identifiers, routing, immutability, multi-version baseline, dual-product positioning, plugin architecture, etc.
- [Roadmap / development plan](./docs/roadmap/development-plan.md)

## Extend it — 14+ public extension points, zero forks

Every visible feature is a thin shell over a contract you can re-implement.

| You want to… | Implement | Methods | Time |
|---|---|---|---|
| Admin a new data source (Stripe, your microservice, a queue) | `DataSourceInterface` | **3** read + 3 write | 1-2 hours |
| Plug a custom backend into Cmd+K search | `SearchProviderInterface` | **3** | 30 min |
| Pipe audit log to Splunk / Datadog / a SIEM | `AuditLoggerInterface` | **1** | 15 min |
| Render a custom dashboard tile | `WidgetInterface` | **5** | 1 hour |
| Persist saved views in Redis instead of Doctrine | `SavedViewStorageInterface` | **5** | 1 hour |
| Replace the permission backend (LDAP / OPA / custom voter) | `PermissionInterface` | **1** | 15 min |
| Ship a self-contained capability bundle | `AdminPluginInterface` + `#[AsPlugin]` | **3 metadata** | 1 hour |

**Most contracts are 1-5 methods.** That's the design budget — past 5, we open an ADR (cf. [ADR-010](./docs/adr/0010-core-api-surface-criterion.md)).

→ **Full extensibility map**: [`docs/user/extensibility.md`](./docs/user/extensibility.md) — 14 extension points with sample code, registration pattern, and "anti-patterns we explicitly resist".

→ **Cookbook**: [build your own adapter](./docs/user/cookbook/build-your-own-adapter.md) — patterns we learned shipping the 6 bundled adapters.

## Quality bar

- **674 unit + functional tests / 1684 assertions** in the package matrix, plus 27 integration tests in the showcase
- **PHPStan level max** across all packages
- **PHP-CS-Fixer** PSR-12 + Symfony rules
- **Core coverage gate ≥ 90%** (`polysource/core` sits at 99.17%)
- **CI matrix** runs PHP 8.1/8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0

## Contributing

- Read [`CONTRIBUTING.md`](./CONTRIBUTING.md) for the development workflow.
- Strict scope per [ADR-012](./docs/adr/0012-dual-product-positioning.md) — feature requests outside the dual-product positioning are politely declined.
- See [`docs/strategy/product-vision.md`](./docs/strategy/product-vision.md) §2 before opening a feature request.

## License

[MIT](./LICENSE)
