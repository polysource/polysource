# Roadmap

> What's shipped, what's next. Versions follow [SemVer](https://semver.org)
> from v1.0; pre-1.0 minors may break the surface where the changelog
> says so.

## Shipped

### v0.1 — first public release (2026-05-10)

**16 packages** distributed on Packagist as `polysource/<pkg>`,
mirrored from this monorepo via the
[`polysource-split`](./docs/adr/0026-monorepo-split-and-packagist-mirrors.md)
GitHub App. Full multi-version CI matrix
(PHP 8.1→8.4 × Symfony 6.4 / 7.2 / 7.4 × EasyAdmin 4.24 / 5.0).

Capabilities at v0.1:

- **Two products, same primitives** (cf.
  [ADR-012](./docs/adr/0012-dual-product-positioning.md)):
  - `polysource/easyadmin-filter-bridge` — drop-in for an existing
    EasyAdmin app (4.24+ or 5.0+)
  - `polysource/symfony-bundle` (+ adapters) — Polysource standalone
    admin engine for non-Doctrine resources
- **6 adapters**: Messenger, Doctrine read-only, Redis, Flysystem
  (S3 / local / MinIO / Azure / GCS), HTTP REST, Meilisearch
- **5 cross-cutting capabilities**: audit log, dashboard widgets,
  Cmd+K global search, Symfony Workflow bridge, async bulk actions
- **Filter primitives** + saved views (private / team / public) +
  tabbed filter modal + chips bar — usable standalone or via the
  EA bridge

### v0.2 → v0.5 — feature consolidation (May 2026)

Roughly two months of focused dogfooding on a real client app
surfaced and resolved a steady stream of install-path, multi-tenant,
and multi-kernel issues. Major themes shipped across these minors:

- **v0.2** — simplification + progressive enhancement (ADR-027) +
  scope discipline (ADR-028)
- **v0.3** — Tier 1 game-changers (saved-views polish, filter URL
  tokens, bulk async progress UI)
- **v0.4** — Tier 1 game-changers continued (deeper UX work)
- **v0.5** — Sprint 1 filter-aware export + bulk dry-run count,
  inter-package constraint union audits, multi-tenant + multi-kernel
  install safety, IN / NOT IN / IS [NOT] NULL filter coverage,
  Doctrine ORM 2.x + 3.x compat (v0.5.0 → v0.5.7)

### v0.6 — v0.6.0 (2026-05-15)

Three backlog issues closed plus features consolidating the dogfooding
lessons into permanent tooling:

- **`polysource:doctor`** self-diagnostic command
  ([#29](https://github.com/polysource/polysource/issues/29))
- **`extra.symfony.controllers`** advertisement on the 3 packages
  shipping Stimulus controllers for AssetMapper auto-discovery
  ([#30](https://github.com/polysource/polysource/issues/30))
- **Functional integration tests** for the bridge with TestKernel +
  SQLite covering the 3 HTTP entry points
  ([#31](https://github.com/polysource/polysource/issues/31))
- **Periodic purge commands** for `filter_url_tokens` + `bulk_action_history`
- **`ListWidget` accepts a per-request closure** for dashboards
  needing live data
- **i18n docs** + **CSP / WCAG 2.2 AA audit page** + **Flex recipes
  prep** + **TestKernel patterns guide** + **Sf 5.4 → 8.0 compat audit**

### v0.7 — public API freeze prep (v0.7.0 → v0.7.3, 2026-05-15/16)

Closed the 6 outstanding items from
[ADR-011](./docs/adr/0011-pre-v1.0-freeze-checklist.md) so that v1.0
can ship without API drift — notably `FilterCriterion::$operator:
string` → `enum FilterOperator` (breaking, signalled) and the
[AdminContext decomposition design ADR-029](./docs/adr/0029-admin-context-decomposition.md).

### v0.8 — Redis adapter reshape (v0.8.0 → v0.8.2, 2026-05-16/17)

5 type-pure Redis data sources (string / list / hash / set /
sorted-set) closing the dogfood-surfaced `WRONGTYPE` crash, plus the
shared `FilterUrlBuilder` as single source of truth for the
`filters[...]` URL shape.

### v0.9 — architectural cleanup (v0.9.0 2026-05-18, v0.9.1 2026-08-06)

Full-codebase audit: 17 of 22 findings resolved (CSRF on saved-view
routes, open-redirect + XSS hardening, decompositions — tracker in
[docs/maintainers/v0.9.0-architectural-cleanup.md](./docs/maintainers/v0.9.0-architectural-cleanup.md)).
v0.9.1 thawed the dependency freeze and cleaned the per-package dist
(explicit deps, export-ignores, derived plugin versions).

## Next

### v0.10 — remaining large refactors (in progress)

The audit items deferred from v0.9 with documented rationale, plus
host-app genericity work:

- **Bridge theming / i18n / CSP** — bundle stylesheet with
  `--polysource-*` variables, full EN+FR catalogs, no inline
  CSS/JS (shipped on main, releases with v0.9.2/v0.10)
- **`OperatorTranslator`** — deduplicate the FilterOperator → query
  translation across the 6 adapters
- **`FeatureGate` DI predicates** — finish the migration started in
  [#71](https://github.com/polysource/polysource/pull/71)

### Pre-1.0 polish (not committed)

- ORM 3.x CI matrix row (gap identified in the
  [Symfony compat audit](./docs/maintainers/symfony-compat-audit.md))
- Sf 8.0 alpha smoke gate when upstream ships
- Showcase rewrite to demo v0.6+ capabilities
  (`polysource:doctor`, purge commands, ListWidget closure, CSP nonce)
- Sonata Admin filter bridge (community-driven)
- Wider i18n coverage (community-driven, EN+FR ship today —
  cf. [docs/user/i18n.md](./docs/user/i18n.md))

## v1.0 — API freeze

The public surface (`Polysource\Plugin\*` + the documented
`*Interface` types in each package) freezes on v1.0 per
[ADR-018](./docs/adr/0018-admin-plugin-interface-and-public-contracts.md).
SemVer applies strictly from that point on; breaking changes ship
in major versions only. Floor targets per
[ADR-011](./docs/adr/0011-pre-v1.0-freeze-checklist.md):

- PHP 8.2+ (drops 8.1)
- Symfony 6.4 LTS (drops 5.4 / 6.0)
- EasyAdmin 4.24+ retained
- Doctrine ORM 2.16+, 3.x recommended

## Out of scope

The boundary stays where it always was (cf.
[ADR-012](./docs/adr/0012-dual-product-positioning.md),
[ADR-017](./docs/adr/0017-cherry-picking-from-filament-study.md),
[ADR-028](./docs/adr/0028-scope-discipline.md)):

- Replacing EasyAdmin on Doctrine ORM
- BI dashboards (Grafana / Metabase territory)
- No-code internal-tool builders (Retool / Appsmith territory)
- Multi-tenant SaaS hosting
- Authentication providers (use the host app's firewall)
- Kanban / chatter / reporting features (admin platform alternative
  territory)

## Want to help?

The launch will publish a set of "help wanted" issues at v1.0. In
the meantime, draft contributions on the topics above (especially
the Sonata bridge and the ORM 3.x CI row) are welcome — comment on
the relevant issue or open a draft PR.
