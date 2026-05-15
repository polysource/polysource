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
| **🔌 Extend Polysource — 14+ extension points, zero forks** | [**extensibility.md**](./extensibility.md) |
| **See Polysource in action with screenshots** | [showcase-tour.md](./showcase-tour.md) |
| Install Polysource in a Symfony 5.4+ app (any minor since 5.4) | [installation.md](./installation.md) |
| Get a working dashboard in 5 minutes | [getting-started.md](./getting-started.md) |
| Understand the building blocks | [concepts/](./concepts/) |
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
| Copy-paste a runnable recipe | [cookbook/](./cookbook/) |
| Look up a public interface signature | [api/](./api/) |

## What's in this folder

```
docs/user/
├── README.md                  ← you are here
├── installation.md            ← composer require + bundle registration
├── getting-started.md         ← five-minute path to a working dashboard
├── i18n.md                    ← EN + FR ship out of the box; how to add ES/DE/…
├── security-and-a11y.md       ← CSP (style-src nonce) + WCAG 2.2 AA coverage audit
├── concepts/
│   ├── resource.md            ← what a Resource is
│   ├── data-source.md         ← read/write contracts (3 + 3 methods)
│   ├── field.md               ← how columns get rendered
│   ├── action.md              ← inline / bulk / global actions
│   ├── permission.md          ← Symfony Security integration
│   └── plugin.md              ← AdminPluginInterface + #[AsPlugin] (ADR-018)
├── adapters/
│   └── messenger.md           ← reference for polysource/adapter-messenger
├── filter/                    ← standalone polysource/filter primitive
│   ├── README.md
│   └── getting-started.md
├── easyadmin-filter-bridge/   ← drop-in for EasyAdmin (4.24+ or 5.0+)
│   ├── getting-started.md
│   └── whats-new.md
├── cookbook/
│   ├── messenger-failed-dashboard.md
│   ├── adding-a-custom-action.md
│   └── permissions-with-roles.md
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
  [ROADMAP](../../ROADMAP.md) and the [pre-v1.0 freeze checklist (ADR-011)](../adr/0011-pre-v1.0-freeze-checklist.md).

## Status

Polysource **v0.1.0** is published (2026-05-10). 16 packages are
distributed on Packagist as `polysource/<pkg>`, mirrored from the
[`polysource/polysource`](https://github.com/polysource/polysource)
monorepo via the automated subtree-split pipeline documented in
[ADR-026](../adr/0026-monorepo-split-and-packagist-mirrors.md). The
public API is **release-candidate stable** — committed for v0.1.x,
SemVer-frozen only at v1.0. Breaking changes between minors are
allowed and signalled in the [CHANGELOG](../../CHANGELOG.md).

**Quality bar (v0.1.0 — 2026-05-10)**: 782 unit + functional tests /
1932 assertions in the package matrix, 29 Panther browser E2E tests,
and 15 adapter integration tests on real containers (Redis, S3 MinIO,
Meilisearch, HTTP API). PHPStan max + cs-fixer clean. CI runs PHP
8.1/8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0.

## License

MIT — see [`LICENSE`](../../LICENSE).
