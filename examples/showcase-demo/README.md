# Polysource Showcase Demo — ShopCo SaaS

> **Hero of the v0.1.0 launch.** A single Symfony 7.4 LTS application
> exercising the **15 Polysource packages** in a coherent
> e-commerce SaaS scenario. See
> [ADR-025](../../docs/adr/0025-showcase-demo.md) for the rationale.

**Status — Phase 23-A** : bootstrap. Boots, login works, "Phase A
bootstrap OK" home page. Entities, EasyAdmin CRUDs, Polysource
resources, dashboards, search, bulk async, audit — all land in
sub-phases B → J.

## Run

```bash
# From the repo root
make showcase
```

Then open http://localhost:8084/.

| Login | Password | Role | Sees |
|---|---|---|---|
| `admin@shop.co` | `shopco` | `ROLE_ADMIN` | Everything |
| `ops@shop.co` | `shopco` | `ROLE_OPS` | Bulk jobs, retries, transitions |
| `viewer@shop.co` | `shopco` | `ROLE_VIEWER` | Read-only |

## Service map

| Service | URL | Used by |
|---|---|---|
| Showcase HTTP | http://localhost:8084/ | the app |
| Postgres 17 | `localhost:5435` | doctrine, audit, bulk-async, saved views |
| Redis 7 | `localhost:6382` | symfony cache + `adapter-redis` |
| Meilisearch 1.x | http://localhost:7702/ | `adapter-meilisearch` |
| MinIO (S3) | http://localhost:9101/ | `adapter-flysystem` |
| Mercure | proxied via nginx | `bulk-async` live progress |
| Mailpit | http://localhost:8025/ | SMTP capture |
| WireMock | http://localhost:8089/__admin/ | `adapter-http` microservices simulation |

## Stack — irréprochable 2026

| Layer | Choice |
|---|---|
| PHP | 8.4 |
| Symfony | 7.4 LTS |
| EasyAdmin | 5.x |
| Doctrine ORM | 3.x |
| Foundry | 2.x static factories |
| Asset pipeline | AssetMapper (Symfony native) |
| Frontend | Symfony UX (Stimulus + Turbo + Twig Components) |
| DB | PostgreSQL 17 |
| Cache / search / files / live / mail / mocks | Redis 7 / Meilisearch 1.x / MinIO / Mercure / Mailpit / WireMock |
| E2E + screenshots | Symfony Panther |

See [ADR-025 §3](../../docs/adr/0025-showcase-demo.md#3-stack-technique-état-de-lart-2026)
for the justification of every choice and the alternatives rejected.

## Make targets

```text
make build        Build the PHP container image
make install      Install Composer deps
make up           Boot the stack + serve on :8084
make db           Reset + migrate the database
make fixtures     Load Foundry fixtures (Phase B+)
make assets       Compile AssetMapper public/ files
make down         Stop the stack (preserves volumes)
make reset        Stop and wipe ALL volumes (data loss)
make logs         Tail logs from all services
make shell        Shell in the PHP container
make test         PHPUnit + Panther
make phpstan      Static analysis level max
make cs-fix       PSR-12 + Symfony rules
make screenshots  Regenerate docs/user/screenshots/ (Phase I+)
make clean        Stop + remove vendor/, var/, build artefacts
```

## Phase progression (cf. development-plan.md §Phase 23)

- ✅ **A** — Bootstrap (this commit)
- ⏳ **B** — Domain entities + Foundry stories
- ⏳ **C** — EasyAdmin CRUDs + filter-bridge wired
- ⏳ **D** — Polysource standalone wiring (messenger + doctrine adapter)
- ⏳ **E** — 4 non-Doctrine adapters (redis, flysystem, http, meilisearch)
- ⏳ **F** — Cross-cutting: audit, workflow, widgets, search, bulk-async
- ⏳ **G** — 3-role permissions + saved views + UX polish
- ⏳ **H** — Panther E2E suite
- ⏳ **I** — Screenshots pipeline
- ⏳ **J** — Doc rewrite EA-style + launch announcements
