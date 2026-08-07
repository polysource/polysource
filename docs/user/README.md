# Polysource — user documentation

Polysource is a Symfony admin engine for resources that don't live in a
Doctrine ORM database: Symfony Messenger failed messages, feature flags,
files (S3 / local), external REST APIs, Meilisearch documents, jobs,
webhooks, YAML/JSON configuration files.

It is **not** a fork of EasyAdmin and **not** a competitor. It is
designed to **complement** existing Symfony admin tools by addressing
what Doctrine-ORM-centric solutions don't cover.

## Where to start

| If you want to… | Read |
|---|---|
| **🔌 Extend Polysource — 16+ extension points, zero forks** | [**extensibility.md**](./extensibility.md) |
| **See Polysource in action with screenshots** | [showcase-tour.md](./showcase-tour.md) |
| Install Polysource in a Symfony 6.4+ app (any minor since 6.4) | [installation.md](./installation.md) |
| Upgrade an existing app from v0.5 / v0.6 to v0.7 | [upgrade/v0.5-to-v0.7.md](./upgrade/v0.5-to-v0.7.md) |
| Get a working dashboard in 5 minutes | [getting-started.md](./getting-started.md) |
| Understand the building blocks | [concepts/](./concepts/) |
| Expand a row in place to show detail content (native listing) | [row-details.md](./row-details.md) |
| Same, on an EasyAdmin listing | [easyadmin-filter-bridge/row-details.md](./easyadmin-filter-bridge/row-details.md) |
| Wire the Messenger failed transport | [adapters/messenger.md](./adapters/messenger.md) |
| Admin a Doctrine entity (cohabitation case) | [adapters/doctrine.md](./adapters/doctrine.md) |
| Admin Redis hash collections (feature flags, config) | [adapters/redis.md](./adapters/redis.md) |
| Admin files on S3 / local / Azure / GCS via Flysystem | [adapters/flysystem.md](./adapters/flysystem.md) |
| Admin external REST APIs (Stripe, GitHub, internal microservices) | [adapters/http.md](./adapters/http.md) |
| Admin a Meilisearch index (browse, search, manual corrections) | [adapters/meilisearch.md](./adapters/meilisearch.md) |
| Add a GDPR Art. 30 / HIPAA audit trail | [audit/](./audit/) |
| Wire Symfony Workflow into your admin (auto transitions + state chip) | [workflow-bridge/](./workflow-bridge/) |
| Compose admin dashboards with KPI / list / chart widgets | [widgets/](./widgets/) |
| Add a Cmd+K command palette for cross-resource search | [search/](./search/) |
| Run bulk actions asynchronously with live progress + cancel | [bulk-async/](./bulk-async/) |
| Build filter UIs (standalone primitive) | [filter/](./filter/) |
| Enrich an existing EasyAdmin app's filters — 4.24+ or 5.0+ (install + walk-through) | [easyadmin-filter-bridge/getting-started.md](./easyadmin-filter-bridge/getting-started.md) |
| Honest per-filter matrix vs upstream EA | [easyadmin-filter-bridge/whats-new.md](./easyadmin-filter-bridge/whats-new.md) |
| Retheme the bridge (CSS variables, template overrides, CSP) | [easyadmin-filter-bridge/theming.md](./easyadmin-filter-bridge/theming.md) |
| Copy-paste a runnable recipe | [cookbook/](./cookbook/) |
| Look up a public interface signature | [api/](./api/) |

> **Upgrading from a pre-v1.0 lineage?** v1.0.0 raised the floors to
> PHP 8.2+ and Symfony 6.4 LTS+ (ADR-011) — apps still on PHP 8.1 or
> Symfony 5.4 must move up before installing v1.x. The one upgrade
> guide currently written is
> [upgrade/v0.5-to-v0.7.md](./upgrade/v0.5-to-v0.7.md); for everything
> else the per-release detail is in the
> [CHANGELOG](../../CHANGELOG.md).

## What's in this folder

```
docs/user/
├── README.md                  ← you are here
├── installation.md            ← composer require + bundle registration
├── getting-started.md         ← five-minute path to a working dashboard
├── extensibility.md           ← the 16+ extension points, one page
├── showcase-tour.md           ← screenshot-by-screenshot tour of the demo app
├── row-details.md             ← expandable row details on a native listing (v1.1)
├── i18n.md                    ← EN + FR ship out of the box; how to add ES/DE/…
├── security-and-a11y.md       ← CSP (style-src nonce) + WCAG 2.2 AA coverage audit
├── concepts/
│   ├── resource.md            ← what a Resource is
│   ├── data-source.md         ← read/write contracts (3 + 3 methods)
│   ├── field.md               ← how columns get rendered
│   ├── action.md              ← inline / bulk / global actions
│   ├── permission.md          ← Symfony Security integration
│   └── plugin.md              ← AdminPluginInterface + #[AsPlugin] (ADR-018)
├── adapters/                  ← one page per bundled adapter
│   ├── doctrine.md
│   ├── flysystem.md
│   ├── http.md
│   ├── meilisearch.md
│   ├── messenger.md
│   └── redis.md
├── audit/                     ← polysource/audit (README + installation + walkthrough + extending)
├── bulk-async/                ← polysource/bulk-async (same 4-page shape)
├── widgets/                   ← polysource/widgets (README + installation + walkthrough)
├── search/                    ← polysource/search (README + installation + walkthrough)
├── workflow-bridge/           ← polysource/workflow-bridge (same 4-page shape)
├── filter/                    ← standalone polysource/filter primitive
│   ├── README.md
│   ├── getting-started.md
│   ├── saved-views.md
│   ├── column-preferences.md
│   ├── recent-records.md
│   └── bulk-action-history.md
├── easyadmin-filter-bridge/   ← drop-in for EasyAdmin (4.24+ or 5.0+)
│   ├── getting-started.md
│   ├── whats-new.md
│   ├── theming.md
│   ├── row-details.md         ← expandable row details on an EA listing (v1.1)
│   ├── row-styles.md
│   ├── row-density.md
│   ├── frozen-columns.md
│   ├── column-reorder.md
│   ├── saved-column-configurations.md
│   ├── filter-aware-export.md
│   ├── filter-deep-linking.md
│   ├── keyboard-shortcuts.md
│   └── toasts.md
├── cookbook/
│   ├── messenger-failed-dashboard.md
│   ├── adding-a-custom-action.md
│   ├── permissions-with-roles.md
│   ├── build-your-own-adapter.md
│   └── wrapping-in-easyadmin-chrome.md ← mix Polysource standalone with EA sidebar/header
├── upgrade/
│   └── v0.5-to-v0.7.md
├── screenshots/               ← PNGs embedded by showcase-tour.md
└── api/
    └── README.md              ← public interfaces at a glance
```

## What this doc does *not* cover

- **Why** Polysource exists and which scope it takes — read
  [`docs/strategy/product-vision.md`](../strategy/product-vision.md).
- **How** the architecture is structured — read
  [`docs/architecture/target-architecture.md`](../architecture/target-architecture.md).
- **Why** every binding choice was made — read the
  [ADRs](../adr/).
- **Where** the project is going — read the
  [ROADMAP](../../ROADMAP.md). The historical pre-v1.0 freeze checklist
  ([ADR-011](../adr/0011-pre-v1.0-freeze-checklist.md)) was closed out
  at v1.0.0 and is kept for the record.

## Status

Polysource **v1.1.0** is published (2026-08-07). 16 packages are
distributed on Packagist as `polysource/<pkg>`, mirrored from the
[`polysource/polysource`](https://github.com/polysource/polysource)
monorepo via the automated subtree-split pipeline documented in
[ADR-026](../adr/0026-monorepo-split-and-packagist-mirrors.md). The
public API has been **frozen since v1.0.0** (2026-08-06) and the
project follows **strict SemVer** — breaking changes are reserved for
a future major and every release is detailed in the
[CHANGELOG](../../CHANGELOG.md).

**Quality bar**: the root [README](../../README.md#quality-bar) is the
single source of truth for the test counts. At v1.1.0 that is 1256 unit
+ functional tests and 28 integration tests in the package matrix, 47
Symfony Panther browser E2E tests plus 24 showcase WebTestCase tests,
and 15 adapter integration tests on real containers (Redis, S3 MinIO,
Meilisearch, HTTP API). PHPStan max + cs-fixer clean. CI runs PHP
8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0.

## License

MIT — see [`LICENSE`](../../LICENSE).
