# Polysource Admin

> Symfony admin panels for resources beyond Doctrine.

**Polysource Admin** is a Symfony admin toolkit for resources that are not necessarily Doctrine entities: Messenger failed messages, feature flags, files, queues, external APIs, search indexes, webhooks and configuration resources.

---

## Status — pre-v0.1.0

Phases 1 through 9.7 are shipped on `main` (core + symfony-bundle + twig-theme +
adapter-messenger + filter + easyadmin-filter-bridge + 4 runnable demos). The
public API is stable but not tagged — see
[`docs/roadmap/development-plan.md`](./docs/roadmap/development-plan.md) for the
phase status, and the [ADRs](./docs/adr/) for the choices that landed since.

## Run the demos

| Demo | Stack | Port | Audience |
|---|---|---|---|
| `make demo` | PHP 8.4 + Sf 7.4 + Doctrine | `:8080` | Messenger failed-messages dashboard (the v0.1 flagship) |
| `make demo-bridge` | PHP 8.4 + Sf 7.4 + EA 5 | `:8081` | EasyAdmin v5 hosts adopting the bridge |
| `make demo-bridge-v4` | PHP 8.1 + Sf 6.4 LTS + EA 4.29 | `:8083` | EA v4 hosts that haven't migrated — proves the floor of the multi-version baseline (cf. [ADR-015](./docs/adr/0015-multi-version-compatibility-baseline.md)) |
| `make demo-filter` | Vanilla Sf 6.4 + PHP 8.1, **no EasyAdmin** | `:8082` | Sonata users, API Platform back-offices, hand-rolled admin DIY — uses the standalone `polysource/filter` primitive |

Login on the EA demos: `admin` / `admin`. Each demo is a self-contained
Docker image; first `make demo*` triggers a one-time build. See each demo's
`README.md` for the per-demo walkthrough.

## Why Polysource

Most Symfony admin engines (EasyAdmin, Sonata, etc.) are designed around Doctrine ORM entities. They're excellent for that — but they don't naturally cover:

- **Messenger failed messages** (no built-in UI to retry/dismiss)
- **Feature flags** stored in Redis or a custom store
- **Files** on local filesystem or S3
- **External REST APIs** that you operate but don't own the schema of
- **Meilisearch / Elasticsearch documents**
- **Background jobs**, **webhooks**, **YAML/JSON configuration**

Polysource Admin is built **for those cases**.

## What it is **not**

- **Not a fork of EasyAdmin.** EasyAdmin is excellent for Doctrine CRUD; we don't try to replace it.
- **Not a frontal competitor to EasyAdmin.** For Doctrine entities, you should keep using EasyAdmin. Polysource will offer an optional EasyAdmin bridge so the two can coexist.
- **Not a Doctrine-first CRUD generator.** Doctrine is just one adapter among many.
- **Not a no-code internal-tool builder** (use Retool/Appsmith for that).
- **Not a SaaS** — Polysource is a self-hosted Symfony bundle, MIT-licensed.

## Initial target use cases

The first release (v0.1) ships one real, visible use case:

> A dashboard for **Messenger failed messages** with retry / retry-all / dismiss, in less than 5 minutes of setup.

Subsequent releases will add adapters one at a time, each driven by a real user need:

| Adapter | Target version | Use case |
|---|---|---|
| `polysource/adapter-messenger` | v0.1 | failed messages, queues |
| `polysource/adapter-doctrine` | v0.2 | cohabit with EasyAdmin, simple Doctrine resources |
| `polysource/adapter-flysystem` | v0.2 | local files, S3 |
| `polysource/adapter-http` | v0.3 | external REST APIs |
| `polysource/adapter-redis` | v0.3 | feature flags, Redis hashes |
| `polysource/easyadmin-bridge` | v0.3 | bidirectional integration |
| `polysource/adapter-meilisearch` | v1.0 | search indexes |
| `polysource/adapter-config` | v1.0 | YAML / JSON config files |

## Long-term vision

Polysource provides a minimal, well-defined contract (`DataSourceInterface` with 3 methods, plus `WritableDataSourceInterface` for writes) inspired by Sylius Grid and React Admin. Adapters are independent Composer packages, registered via Symfony service tags. The UI is a Twig theme borrowed and adapted from EasyAdmin v5 (MIT-compatible).

Filament-style fluent builders (`Form::schema([...])`, `Table::columns([...])`) are planned for the DX layer once the core contract is stable.

## Architecture summary

```
polysource/
├── core/                 contracts + value objects, no Symfony deps
├── symfony-bundle/       wiring (DI, routing, ArgumentResolvers, Twig)
├── twig-theme/           default UI templates
├── adapter-messenger/    first adapter (failed messages)
└── easyadmin-bridge/     coexistence with EasyAdmin
```

Read the full design in [`docs/architecture/target-architecture.md`](./docs/architecture/target-architecture.md).

## Documentation

The strategy and architecture analysis live entirely in [`docs/`](./docs/README.md):

- [Index](./docs/README.md)
- [Architecture cible (signatures PHP, flux, adapters)](./docs/architecture/target-architecture.md)
- [Plan de développement (Phases 0–10)](./docs/roadmap/development-plan.md)
- [Vision produit](./docs/strategy/product-vision.md)
- [Architecture Decision Records](./docs/adr/)

## What's next

1. Review and validate [`docs/roadmap/development-plan.md`](./docs/roadmap/development-plan.md).
2. Once approved, scaffold `packages/core` (interfaces + value objects).
3. Scaffold `packages/symfony-bundle` and `packages/twig-theme`.
4. Build `packages/adapter-messenger` with retry/dismiss demo.
5. Tag `v0.1.0` once the demo runs end-to-end.

## Contributing

This project is in design phase — no PRs against code yet (there is none). However, **feedback on the documentation is very welcome**:

- Open an issue to discuss architecture choices, adapters you'd want, or DX concerns.
- See [`CONTRIBUTING.md`](./CONTRIBUTING.md) for the development workflow once code lands.

## License

[MIT](./LICENSE)
