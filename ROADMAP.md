# Roadmap

> What's shipped, what's next. Versions follow [SemVer](https://semver.org)
> strictly since v1.0.0 (2026-08-06): breaking changes ship in major
> versions only. (Pre-1.0, minors could break the surface where the
> changelog said so.)

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

### v0.10 — host-app genericity + audit closure (v0.10.0, 2026-08-06)

Shipped the deferred audit refactors and the host-genericity batch:
bridge theming via `--polysource-*` CSS variables (bundle stylesheet,
no inline CSS/JS — CSP-friendly), full EN+FR catalogs (5 → 57 keys),
operator-translation strategy closed and documented
([ADR-0031](./docs/adr/0031-operator-translation-strategy.md)),
`FeatureGate` adoption completed, an open redirect closed in
`FilterUrlTokenController`, and the README rebuilt as an
adoption-first landing page with fresh showcase screenshots.

### v0.11 — FeatureLoader split (v0.11.0, 2026-08-06)

The last open audit item (M2/#67 phase 2):

- **FeatureLoader class split** — decompose the two large DI
  extensions (`PolysourceFilterExtension`,
  `PolysourceEasyAdminFilterBridgeExtension`) into per-feature
  loader classes; convention ratified as
  [ADR-0032](./docs/adr/0032-featureloader-di-decomposition.md)

### v1.0 — API freeze (v1.0.0, 2026-08-06)

The public surface (`Polysource\Plugin\*` + the documented
`*Interface` types in each package) is frozen per
[ADR-018](./docs/adr/0018-admin-plugin-interface-and-public-contracts.md).
SemVer applies strictly since this release; breaking changes ship
in major versions only. The
[ADR-011](./docs/adr/0011-pre-v1.0-freeze-checklist.md) checklist
closed with the tag, and its floors shipped:

- PHP 8.2+ (drops 8.1)
- Symfony 6.4 LTS+ (drops 5.4 / 6.0; `^8.0` allowed by constraints,
  gated in CI since v1.2.0)
- EasyAdmin 4.24+ retained
- Doctrine ORM 2.20+ / 3.6+

### v1.1 — expandable row details (v1.1.0, 2026-08-07)

- **Expandable row details** on the EA bridge
  ([ADR-0033](./docs/adr/0033-expandable-row-details.md)):
  per-entity providers, lazy fragment endpoint, per-row permission
  (entity as voter subject), Stimulus enhancement over a no-JS
  baseline. Read-only per ADR-028.
- **Per-record action gating** in the bundle: voters receive the
  `DataRecord` as subject; `isDisplayed()` gets a real context —
  unblocks per-row action visibility and fixes the workflow-bridge
  transition buttons.
- **Row details on the native theme** (`HasRowDetailsInterface`,
  fifth `detail-panel` route, all six adapters) and **nested
  Polysource listing as detail** (`RowDetail::listing()`, read-only,
  `rd_page` panel-scoped pagination — the context-isolation blocker
  was dissolved by the lazy-fetch renderer design, cf. ADR-0033).
- Deferred explicitly: sorting and user filters *inside* the
  embedded listing (scoping via `parentFilters` only in v1.1).
- **v1.1.1** (2026-08-07) docs truth-sync + the `make docs-check` gate;
  **v1.1.2** (2026-08-20) EasyAdmin 5.5 host-recette patch.

### v1.2 — Symfony 8 / PHP 8.5 (v1.2.0, 2026-08-24)

The `^8.0` constraint every package had carried since v0.6 was an
aspiration nobody had run. This release makes it a tested claim:

- **PHP 8.5 × Symfony 8.1 × EA 5 matrix row** plus an `sf81-ceiling`
  Packagist smoke job, the mirror image of the existing `sf54-floor`.
- **`symfony/mercure` widened** to `^0.6 || ^0.7 || ^0.8 || ^1.0`. The
  old constraint excluded 0.8, the only line supporting Symfony 8, so a
  host on Sf 8 could not use live bulk-job progress at all. This is the
  one item in the release that unblocks an actual host.
- Test doubles made version-proof, the `polysource:doctor` PHP floor
  corrected to 8.2, and the `sf54-floor` job un-stuck from the retired
  0.x lineage it had silently kept testing.
- **No floor moved**, so this is additive: hosts on PHP 8.2 / Sf 6.4
  upgrade with no action. Details and the audit table:
  [Symfony compatibility audit](./docs/maintainers/symfony-compat-audit.md).

## Next

### Backlog (not committed)

- ORM 3.x CI matrix row (gap identified in the
  [Symfony compat audit](./docs/maintainers/symfony-compat-audit.md))
- Showcase rewrite to demo v0.6+ capabilities
  (`polysource:doctor`, purge commands, ListWidget closure, CSP nonce)
- Sonata Admin filter bridge (community-driven)
- Wider i18n coverage (community-driven, EN+FR ship today —
  cf. [docs/user/i18n.md](./docs/user/i18n.md))

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

The launch will publish a set of "help wanted" issues (in
preparation now that v1.0 has shipped). In the meantime, draft
contributions on the topics above (especially
the Sonata bridge and the ORM 3.x CI row) are welcome — comment on
the relevant issue or open a draft PR.
