# Changelog

All notable changes to Polysource are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic
Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `polysource/easyadmin-filter-bridge` — **CRITICAL install blocker**.
  Under the documented happy-path install (`composer require
  polysource/easyadmin-filter-bridge`), every EasyAdmin index page
  with filters applied crashed at render time with
  `Twig\Error\SyntaxError: Unknown "saved_views_dropdown" function in
  @PolysourceEasyAdminFilterBridge/crud/index.html.twig`. Two
  load-bearing bugs combined: (1) the bridge's auto-prepended
  `crud/index.html.twig` referenced a Twig function owned by
  `polysource/symfony-bundle` (not a dep of the bridge), and (2) the
  runtime gate `polysource_saved_views_available()` returned true on
  bare installs because it checked `class_exists(SavedViewExtension)
  && interface_exists(EntityManagerInterface)` — both true via
  transitive deps. Even with the gate corrected, Twig resolves
  function names at parse time independently of `{% if %}` guards, so
  the template still failed to compile when the function was not
  registered. Fix: register a silent stub for `saved_views_dropdown`
  in `ChipExtension::getFunctions()` when symfony-bundle is absent,
  and correct the runtime gate to `class_exists(PolysourceBundle)` —
  the only honest signal that the real function is registered.
  Adds the previously-missing `ChipExtensionTest` (this extension had
  zero coverage before).

### Added

- `polysource/easyadmin-filter-bridge` — `PolysourceFilter` now
  proxies EasyAdmin's `FilterTrait` fluent setters (`setLabel`,
  `setProperty`, `setFormType`, `setFormTypeOption`,
  `setFormTypeOptionIfNotSet`, `setFormTypeOptions`). The documented
  flagship example in `whats-new.md` and `getting-started.md`
  (`Polysource::filter($f)->tab()->group()->setFormTypeOption(...)`)
  crashed at runtime before this change with `Attempted to call an
  undefined method named "setFormTypeOption" of class
  "PolysourceFilter"`. Explicit typed proxies were chosen over a
  generic `__call` for IDE autocomplete, static analysis, and clarity
  about what is and isn't proxied. Each proxy writes through to the
  wrapped filter's `FilterDto` (same surface EA's own setters target),
  returning `$this` to preserve fluent chaining.
- `scripts/smoke-packagist-bridge.sh` + `make smoke-packagist-bridge`
  — bridge-alone install smoke test. Mirrors `smoke-packagist.sh`
  but exercises the `composer require polysource/easyadmin-filter-bridge`
  path **without** `polysource/symfony-bundle`, then runs
  `lint:twig` on the bridge's prepended templates. This is the
  regression guard that would have caught the v0.1.1 install blocker
  before tag (the existing `smoke-packagist.sh` masked the bug by
  installing symfony-bundle, which registers the `saved_views_dropdown`
  Twig function — the bridge-alone path is what users actually hit).
  Run after every release that touches the bridge.

### Docs

- New "JavaScript / Stimulus prerequisite" section in
  `docs/user/installation.md` (top-level prereq table) and in
  `docs/user/easyadmin-filter-bridge/getting-started.md` /
  `docs/user/filter/getting-started.md` (per-package guides).
  Documents which features need Stimulus, which work server-side,
  and how to register the controllers manually in v0.1.x — until
  `extra.symfony.controllers` auto-discovery lands in v0.2.

### Known limitations (v0.1.2)

- **Stimulus controller auto-discovery is NOT shipped yet.** Polysource
  packages (`polysource/filter`, `polysource/easyadmin-filter-bridge`,
  `polysource/bulk-async`, `polysource/search`) ship Stimulus
  controllers under `assets/controllers/` but do not declare them via
  `extra.symfony.controllers` in their `composer.json`. The reason
  is non-trivial: the controllers use deliberate Stimulus identifiers
  (`polysource--filter`, `polysource--filter-modal-layout`, etc.)
  that do not match the `<vendor>--<package>--<short-name>`
  convention that `@symfony/stimulus-bridge` auto-discovery generates.
  Adding the manifest naively would auto-load controllers under
  identifiers that the bridge templates never invoke. The proper fix
  (rename identifiers OR adopt a different auto-discovery mechanism)
  requires an ADR and is scheduled for v0.2. Until then, hosts
  register the controllers manually in their Stimulus app — see the
  Stimulus prerequisite section in each package's getting-started.

## [0.1.1] — 2026-05-10

**Install fix — please upgrade from v0.1.0.** v0.1.0 was uninstallable
under Composer's default `minimum-stability: stable` because the 14
sub-packages with inter-package dependencies advertised
`polysource/core: 0.1.x-dev` instead of `^0.1`. A vanilla
`composer require polysource/symfony-bundle` failed to resolve because
the dev-only constraint is incompatible with stable mode.

This release retags the same code as v0.1.0 with the constraints
fixed. The local-monorepo dev workflow is unaffected (Composer's
`branch-alias dev-main → 0.1.x-dev` still satisfies `^0.1` under
`minimum-stability: dev`).

### Fixed

- `polysource/{adapter-doctrine, adapter-flysystem, adapter-http,
  adapter-meilisearch, adapter-messenger, adapter-redis, audit,
  bulk-async, easyadmin-filter-bridge, filter, search,
  symfony-bundle, widgets, workflow-bridge}` — replaced inter-package
  constraint `0.1.x-dev` with `^0.1` so `composer require` works in
  Composer's default stable mode.

### Tooling

- New `scripts/smoke-packagist.sh` (+ `make smoke-packagist` target)
  installs `polysource/symfony-bundle` from Packagist (no path repos)
  on a vanilla Symfony 7.4 skeleton and verifies the bundle boots.
  This script caught the v0.1.0 install bug; it must run before every
  release tag from now on.

## [0.1.0] — 2026-05-10

First public release. 16 packages distributed on Packagist as
`polysource/<pkg>`, mirrored from the `polysource/polysource`
monorepo via the automated subtree-split pipeline (see ADR-026).
Full multi-version CI matrix (PHP 8.1→8.4 × Symfony 6.4/7.2/7.4 ×
EasyAdmin 4.24/5.0), 29 browser E2E + 15 adapter real-container
tests + 782 unit/functional tests (1932 assertions). Released after
the irreproachable-coverage push.

This block captures everything shipped since `0.1.0-alpha.1`.

### Added — saved views (Phase 11)

- `polysource/filter::SavedView` immutable VO + `SavedViewService`
  (rejects duplicate names per `(owner, resource)`), Doctrine and in-memory
  storage adapters, `SavedViewVoter` with 4 attributes
  (VIEW / EDIT / DELETE / SHARE) and 3 scopes (private / team / public).
- Twig extension `saved_views_dropdown(resourceName)` with delete buttons
  scoped to the owner.
- `FilterService::buildUrl()` for shareable filter state.
- EasyAdmin filter bridge: dropdown rendered above the chips bar; Symfony
  voter probed via `polysource_saved_views_available()` so hosts without
  `polysource/filter` get graceful degradation.
- `?view=<id>` apply on Polysource native index pages (symfony-bundle
  `SavedViewApplyListener`) AND EasyAdmin index pages (filter bridge
  `SavedViewApplySubscriber`) — translates the saved criteria into the
  right URL shape per FormType (BooleanFilter bare scalar vs envelope).
- ADR-019 — saved views architecture.

### Added — non-Doctrine action audit log (Phase 12)

- New `polysource/audit` package: `AuditEntry` immutable VO + `AuditOutcome`
  enum + `AuditActorInterface` (`SymfonySecurityAuditActor` default).
- `AuditLoggerInterface` (write-only, 1 method) with fan-out via
  `AggregateAuditLogger` (try/catch isolation + PSR-3 error reporting).
- `DoctrineAuditLogger` + Doctrine entity `AuditEntryRecord` with 3 indexes
  for GDPR Art. 30 queries.
- `ActionAboutToExecuteEvent` + `ActionExecutedEvent` dispatched by
  `polysource/symfony-bundle::ActionController::safelyRun()` (BC-safe).
- `ActionAuditSubscriber` bridging events → logger (UUID v7 per entry,
  IP / UA / RequestID in context, exception trace truncated to 8 KB).
- `AuditLogResource` browsable (#[AsResource], 5 standard filters,
  permission `POLYSOURCE_AUDIT_VIEW`).
- `ExportAuditCsvAction` (12 columns locked, RFC 4180).
- `polysource:audit:purge --before=<datetime>` retention command with
  cutoff exclusive, `--dry-run`, exit codes.
- ADR-020 — audit log architecture.

### Added — Symfony Workflow integration (Phase 13)

- New `polysource/workflow-bridge` package. Separate package so
  `polysource/symfony-bundle` doesn't pull `symfony/workflow` for hosts
  that don't use it.
- `WorkflowAwareInterface` + `WorkflowAwareTrait` (resource opt-in marker).
- `WorkflowResolver` wraps Symfony Workflow `Registry` with graceful
  null-on-failure.
- `TransitionDiscovery` delegates to `Workflow::getEnabledTransitions()` —
  Symfony's native guards stay intact.
- `ApplyTransitionAction` auto-generated per transition with granular
  permission `POLYSOURCE_WORKFLOW_TRANSITION_<UPPER>`.
- `WorkflowChipPalette` + `WorkflowChipExtension` Twig
  (`polysource_workflow_chip_palette()`, `polysource_workflow_state_label()`).
- Configurable palettes via `polysource_workflow_bridge.palettes.<workflow>.<state>`.
- The audit log traces every transition for free
  (`actionName=transition-<name>`).
- ADR-021 — workflow bridge architecture.

### Added — dashboard widgets (Phase 14)

- New `polysource/widgets` package.
- `WidgetInterface` (5 methods) + `AbstractWidget` base.
- 3 concrete widgets: `CounterWidget` (KPI), `ListWidget` (top-N),
  `ChartWidget` (sparkline with textual fallback in v0.1).
- `Dashboard` immutable VO + `DashboardRegistry` (`tagged_iterator
  polysource.widgets.dashboard`, refuses duplicate names).
- `DashboardExtension` Twig: `render_widget()`, `render_dashboard()`,
  `polysource_dashboards()`.
- 4 Bootstrap 5 templates (dashboard layout + counter / list / chart
  partials).
- Drag-drop composition deferred to v0.2 — for now dashboards are
  code-defined.
- ADR-022 — dashboard widgets.

### Added — Cmd+K global search palette (Phase 15)

- New `polysource/search` package.
- `SearchResult` VO + `SearchProviderInterface` (3 methods, deadline
  contract).
- `SearchAggregator` fan-out across tagged providers, 3 contention layers
  (per-provider limit + total budget 250 ms + try/catch isolation).
- `ResourceSearchProvider` default impl wrapping any Polysource resource
  via `DataSource::search()`.
- `SearchController` JSON endpoint `GET /admin/search?q=…`.
- `SearchExtension` Twig (`polysource_search_palette()`).
- Stimulus `cmdk_controller.js` (Cmd+K / Ctrl+K / "/" hooks, debounce
  150 ms, arrow-keys + Enter, Esc close, results grouped per resource).
- Accessible overlay template `_palette.html.twig`.
- Future bridges (`polysource/search-meilisearch`, `-algolia`,
  `-elasticsearch`) extend via the same provider contract.
- ADR-023 — global search palette.

### Added — async bulk actions (Phase 16)

- New `polysource/bulk-async` package.
- `BulkJob` immutable VO (12 fields, 8 KiB error cap) + `BulkJobStatus`
  enum (5 states, `isTerminal()`).
- `BulkJobStorageInterface` (3 methods) + `DoctrineBulkJobStorage` +
  `BulkJobRecord` Doctrine entity (table `polysource_bulk_jobs`, 3 indexes).
- `BulkJobMessage` + `BulkJobHandler` (re-fetches each iteration to honour
  Cancel mid-flight, throttled persist 5 records OR 500 ms, per-record
  exception isolation).
- `AsyncBulkActionDispatcher` host-facing (UUID v7 + Pending persist +
  Messenger dispatch).
- `AsyncAwareBulkActionInterface` opt-in marker (parallel interface, no BC
  break to `BulkActionInterface`).
- `BulkJobResource` browsable (#[AsResource], slug `bulk-jobs`,
  permission `POLYSOURCE_BULK_JOB_VIEW`).
- `CancelBulkJobAction` (idempotent on terminal, gated
  `POLYSOURCE_BULK_JOB_CANCEL`).
- `ProgressController` JSON `GET /admin/bulk-jobs/{id}/progress` (no-cache
  headers).
- `MercureBulkJobBroadcaster` gated on `class_exists(HubInterface)`,
  hub failures swallowed, topic `polysource/bulk-jobs/{id}`.
- Stimulus `progress_controller.js` (EventSource Mercure → polling fallback
  auto on error → `setInterval` 2 s, ETA client-side, stops on terminal).
- Bootstrap 5 progress card + Twig helpers
  `polysource_bulk_progress(job, mercureTopic?)`.
- ADR-024 — bulk async + Mercure architecture.

### Added — 5 adapters (Phases 17–21)

- `polysource/adapter-doctrine` — generic Doctrine ORM read+write with
  whitelist filter properties (Doctrine cohabitation case from ADR-012).
- `polysource/adapter-redis` — `RedisHashClientInterface` (5 methods),
  `PredisRedisHashAdapter` production impl, `InMemoryRedisHashFake`,
  SCAN cursor pagination, client-side filters.
- `polysource/adapter-flysystem` — files on S3 / local / Azure / GCS via
  `FilesystemOperator`, `listContents` with offset emulation,
  mime / size / extension exposure on each `DataRecord`, idempotent
  write + delete.
- `polysource/adapter-http` — REST APIs via Symfony HttpClient,
  `PaginationStrategyInterface` with `PageNumberPaginationStrategy`
  (Stripe-like) and `CursorPaginationStrategy` (GitHub-like),
  `defaultHeaders` for auth, tested with `MockHttpClient`.
- `polysource/adapter-meilisearch` — search-first design,
  `MeilisearchIndexInterface` (4 methods), meilisearch-php production
  adapter, `InMemoryMeilisearchFake` parsing a subset of Meilisearch's
  filter expression syntax, anti-injection filter property sanitisation.

Each adapter follows the same pattern: non-final convenience `*Resource`
base + `*DataSource` implementing `WritableDataSourceInterface` +
`*Bundle` with `#[AsPlugin]` (ADR-018) + `services.php` non-opinionated
(host wires resources).

### Added — build-your-own-adapter cookbook (Phase 22)

- `docs/user/cookbook/build-your-own-adapter.md` synthesising the
  patterns learnt across the 5 bundled adapters: read-only vs writable,
  pagination strategy choice, filter mapping, tiny client interfaces,
  in-memory test fakes, common gotchas.

### Added — showcase demo (Phase 23)

- `examples/showcase-demo/` — hero of the v0.1.0 launch.
- Stack: PHP 8.4, Symfony 7.4 LTS, EasyAdmin 5, Doctrine ORM 3,
  Foundry 2, Postgres 17, Redis, Mercure, Meilisearch, MinIO (S3-compat),
  AssetMapper.
- Seeds: 1 700 Doctrine rows + 50 failed Messenger envelopes + 30 Redis
  cache entries + 15 S3 files + 200 Meilisearch documents + 5 saved views.
- 3 demo accounts (`admin@shop.co`, `ops@shop.co`, `viewer@shop.co`,
  password `shopco`) showcasing role-based permission gating.
- 16 captured screenshots (`docs/user/screenshots/`) regenerated by a
  hardened `app:showcase:screenshots` Panther command (sanity-asserts
  per page, fails loud on empty captures, waits for visible-not-just-present).
- ADR-025 — showcase demo decision.

### Added — extensibility hub

- `docs/user/extensibility.md` — 14+ public extension points,
  1-5 methods each, with sample code, registration pattern, and
  the anti-patterns we explicitly resist (inline editing, polymorphic
  resources, no-code builders, conditional fields).

### Added — i18n

- French translations across all packages with user-visible strings:
  `polysource/symfony-bundle` (umbrella `Polysource` domain hosting
  `polysource/twig-theme`'s strings since the latter is PHP-less),
  `polysource/widgets`, `polysource/search`. Existing French translations
  in `polysource/filter`, `polysource/easyadmin-filter-bridge`,
  `polysource/bulk-async`, `polysource/workflow-bridge` retained.
- 7 packages now declare `symfony/yaml` + `symfony/translation` as hard
  requires so `composer require polysource/<pkg>` works on a vanilla
  Symfony app without auto-pulling those transitively.

### Added — test coverage

- `SavedViewControllerBuildCriteriaTest` — 16 cases covering URL → criteria
  translation per filter type (EA + Polysource shapes).
- `CriteriaToEaQueryTest` — 6 cases covering criteria → URL translation
  per FormType (BooleanFilter bare scalar vs envelope).
- `SavedViewApplySubscriberTest` — 7 cases covering BeforeCrudActionEvent
  lifecycle (missing view, unknown view, cross-resource theft guard,
  empty filter set, redirect with sort+page preserved, BooleanFilter
  bare scalar).
- `SavedViewRoundtripTest` (showcase) — 5 cases full HTTP integration
  through real EasyAdmin OrderCrudController.
- `FieldPermissionEnforcementTest` — pins newly-fixed `Field::setPermission()`
  enforcement.
- `ActionIsDisplayedTest` — pins newly-fixed `Action::isDisplayed()`
  enforcement.
- `CapsAndCsrfEnforcementTest` — 4 cases covering `max_bulk_ids` cap +
  CSRF token enforcement on action endpoints.
- `EasyAdminSmokeTest` — 15 admin routes asserted to render 200.
- `PermissionsByRoleTest` — voter decision matrix across 3 roles ×
  9 attributes.

Subtotal at this point in the [0.1.0] block: **674 unit + functional
tests / 1684 assertions** in the package matrix, plus **27 integration
tests** in the showcase WebTestCase suite. (Final v0.1.0 totals are
quoted at the top of the [0.1.0] section.)

### Added — EasyAdmin CRUD edits in the audit trail

- `EasyAdminAuditSubscriber` (in `polysource/audit`) bridges EA's
  `AfterEntityPersistedEvent`, `AfterEntityUpdatedEvent`,
  `AfterEntityDeletedEvent` to the audit logger. Diff is captured in
  the matching `Before*Event` via Doctrine's
  `UnitOfWork::getEntityChangeSet()` so the `After*` callback gets a
  field-level changes map (old → new).
- The `AuditEntry::context` payload carries `changes` + `snapshot` as
  truncatable JSON (cap 1024 bytes per message column to fit the GDPR
  export schema).
- Gated on `class_exists(AfterEntityUpdatedEvent::class)` — if EA isn't
  installed, the subscriber registers nothing.

### Changed — audit CSV export hardening

- The `Polysource.audit.export.csv` action now declares
  `POLYSOURCE_AUDIT_EXPORT` as its permission (was `null`, which left
  the button hidden in every host security setup).
- Output now starts with the UTF-8 BOM (`\xEF\xBB\xBF`) so Excel /
  Numbers / Calc detect the encoding correctly. Also collapses
  multi-line message bodies to single lines (RFC 4180 quoting still
  applies, but the BOM unblocks consumers that mis-detect UTF-8 as
  Windows-1252 — the `→` mojibake reported on 2026-05-09).
- Sanitises formula triggers (`=`, `+`, `-`, `@`, tab, CR) on every
  cell to defeat CSV injection in spreadsheet readers.

### Fixed — 8 latent bugs surfaced during 2026-05-07 client integration

- Saved-view replay used the wrong URL shape per FormType — BooleanFilter
  is a Symfony ChoiceType so `filters[X][value]=v` was silently ignored
  by the form binding. Now emits `filters[X]=v` for ChoiceType subclasses.
- Saved-view dropdown links truncated EA's `crudControllerFqcn` query
  param.
- `SavedViewController` now accepts both EA and Polysource filter URL
  shapes (16 data-provider cases).
- `Field::setPermission()` was carried on the DTO but never enforced in
  `ControllerSupport::collectFields`. Now filters fields by
  `PermissionInterface::isGranted` per page.
- `Action::isDisplayed()` was on `ActionInterface` but never called.
  Now respected in `collectActionViews`.
- `max_bulk_ids` cap (`polysource.max_bulk_ids` parameter) was declared
  but never enforced on action endpoints.
- CSRF token check was missing on POST action endpoints.
- TEAM-scoped `SavedView::save()` crashed with
  `InvalidArgumentException` when no team resolver was wired. Now
  gracefully falls back to PRIVATE with a flash message.

### Fixed — bridge regressions surfaced during 2026-05-09 showcase QA

- `FilterSessionPersistenceSubscriber` could clobber a
  `SavedViewApplySubscriber` redirect because EA's
  `StoppableEventTrait` is **not** PSR-14 (Symfony's dispatcher does
  not auto-skip listeners after `stopPropagation()`). Both subscribers
  now bail explicitly when `?view=<id>` is in the request or when
  propagation has been stopped — switching saved view A → view B is
  now a single-click operation.
- Multi-select `EnhancedChoiceFilterType` saved with a single `value`
  was replaying as a bare scalar; EA's form binding then rejected the
  shape with "Filter operator cannot be empty". The bridge now
  promotes single-value `eq` criteria to an array when the target
  FormType expects multiple values.
- `NotNullFilter` chip was hidden when the user picked "Any" (the
  no-op tri-state value). Now the chips macro introspects the
  FilterDto FormType and force-renders for `NotNullFilterType` so
  saved-view replay shows the user the filter is in effect.
- `polysource/filter` saved-view dropdown response is no longer
  cached (cache-busting `_t=` query param + `Cache-Control` headers)
  — fixes the report where reopening the dropdown after an apply
  showed a stale list.

### Fixed — CI matrix robustness

- Doctrine ORM 3.x `enableNativeLazyObjects(true)` raised
  `LogicException: Lazy loading proxies require PHP 8.4 or higher` on
  PHP 8.1 / 8.2 / 8.3 matrix rows. Test setups now gate the call on
  `PHP_VERSION_ID >= 80400`; older PHPs use the symfony/var-exporter
  fallback path automatically.
- Symfony Mercure `dev-main` added `getUrl()` and `getProvider()` to
  `HubInterface`; the bulk-async test stubs (`RecordingHub`,
  `ThrowingHub`) now implement them to keep CI green on the bleeding
  matrix row.

### Decided — process ADRs

- ADR-017 — cherry-picking from the exploratory "Filament-for-Symfony"
  study. Locks Phase 11+ to 7 features (plugin architecture, saved
  views, dashboard widgets, bulk async, Symfony Workflow integration,
  global search + command palette, audit non-Doctrine actions). Rejects
  Doctrine-shaped features that would put Polysource in frontal
  competition with EasyAdmin.
- ADR-018 — plugin architecture. `AdminPluginInterface` + `#[AsPlugin]`
  attribute + `PluginRegistry`. Tag-based extension stays primary; the
  interface adds metadata + introspection. SemVer strict on
  `Polysource\Plugin\*` from v1.0.0.
- ADR-019 to ADR-025 — per-capability decisions covering saved views,
  audit, workflow bridge, dashboard widgets, global search, bulk async,
  showcase demo.

## [0.1.0-alpha.1] — 2026-05-05

First public alpha. The shape of the v0.1.0 product is committed; the
public API may still shift before the stable tag.

### Added

- `polysource/core` — pure-PHP contracts (`DataSourceInterface`,
  `ResourceInterface`, `DataQuery`, `DataPage`, `FilterCriterion`, …),
  zero Symfony dep. 99.17 % statement coverage (gate ≥ 90 %).
- `polysource/symfony-bundle` — DI extension, route loader (ADR-003),
  `AdminContext` argument resolver (ADR-004), `#[AsResource]`
  attribute (ADR-005), 3 controllers (index/detail/action), CSRF +
  pagination caps, `SymfonyAuthorizationCheckerPermission`
  (resource-level + action-level voter integration).
- `polysource/twig-theme` — pure-template package (no PHP).
  Layout / index / detail / paginator / 6 field templates, registered
  under the `@Polysource` Twig namespace.
- `polysource/adapter-messenger` — `MessengerFailedDataSource` over
  Symfony Messenger's `ListableReceiverInterface`, `EnvelopeMapper`
  (JSON-first + `print_r` fallback per ADR-006), `FailedMessageResource`
  + 4 actions (retry, dismiss, retry-all, purge) auto-tagged via
  `#[AsResource]`.
- `polysource/filter` — standalone filter primitive: `FilterCollection`,
  `FilterService` (session persistence), enhanced form types (date
  presets, multi-select, between, in), `filter_tags()` Twig function,
  multi-mode UI (simple / integrated / subpanel).
  `Polysource\Filter\Bridge\Contract\ChipFormatterInterface` (ADR-016)
  for cross-bridge chip formatters.
- `polysource/easyadmin-filter-bridge` — drop-in for EasyAdmin
  v4.24+ || v5.0+. `FilterMarkerProcessor` (event subscriber),
  `Polysource::filter()` / `Polysource::field()` proxies, 4 custom
  filters (`BetweenDateFilter`, `InFilter`, `NotNullFilter`,
  `FullTextSearchFilter`), 8 enhancers (presets, quick-ranges,
  multi-select, …), chips bar, 5-stage `ChipValueFormatter` chain.
- 4 runnable demos (`make demo*` from the repo root):
  - `make demo` → Messenger failed-messages dashboard (PHP 8.4 / Sf 7.4).
  - `make demo-bridge` → EasyAdmin v5 + bridge (PHP 8.4 / Sf 7.4 / EA 5).
  - `make demo-bridge-v4` → EasyAdmin v4 + bridge floor proof
    (PHP 8.1 / Sf 6.4 / EA 4.29).
  - `make demo-filter` → standalone primitive on vanilla Symfony
    (PHP 8.1 / Sf 6.4, no EasyAdmin).
- 18 ADRs covering every architectural decision so far.

### Multi-version baseline

Per [ADR-015](docs/adr/0015-multi-version-compatibility-baseline.md) +
2026-05-05 amendment:

- `polysource/core` — no Symfony dep.
- `polysource/twig-theme` — pure templates, no PHP.
- `polysource/symfony-bundle` + `polysource/adapter-messenger` —
  `php >=8.1`, `symfony/* ^6.4 || ^7.0 || ^8.0` (use Sf 6.2+ APIs).
- `polysource/filter` + `polysource/easyadmin-filter-bridge` —
  `php >=8.1`, `symfony/* ^5.4 || ^6.0 || ^7.0 || ^8.0`,
  `easycorp/easyadmin-bundle ^4.24 || ^5.0`,
  `doctrine/orm ^2.20 || ^3.6`. Aligned with EA 4.29's own constraints.

CI matrix: 5 jobs covering PHP 8.1/8.2/8.3/8.4 × Sf 6.4/7.2/7.4 ×
EA 4.x/5.x.

### Status

Pre-v0.1.0. The public API may shift before the stable tag.
Adventurous early adopters can install via Composer using the GitHub
repo as a VCS source:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/polysource/polysource.git" }
  ],
  "require": {
    "polysource/easyadmin-filter-bridge": "0.1.0-alpha.1",
    "polysource/filter": "0.1.0-alpha.1"
  }
}
```

Packagist publication of split repos is planned for the v0.1.0 stable
tag.
