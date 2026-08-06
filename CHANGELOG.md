# Changelog

All notable changes to Polysource are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic
Versioning](https://semver.org/spec/v2.0.0.html).

## [0.11.0] — 2026-08-06

**Audit closed.** The last open item from the v0.9.0 architectural
audit (M2/#67 phase 2) lands: per-feature DI decomposition. Zero
behaviour change. Merged as #111.

### Changed

- **FeatureLoader split (ADR-0032).** The two large DI extensions
  become tables of contents over per-feature loader classes:
  `PolysourceFilterExtension` 440 → 174 lines (7 loaders — pipeline
  core, filter tags, saved views, column preferences, bulk-action
  history, recent records, filter URL tokens);
  `PolysourceEasyAdminFilterBridgeExtension` 549 → 147 lines
  (8 loaders — enhancers, chips, listing-UX helpers, filter tree,
  saved-view controller, export, filter URL tokens, column
  preferences). Each loader owns its feature's entire gate in
  `supports()` and its wiring — with the historical rationale
  comments — in `load()`. Loaders are listed explicitly in the
  extension: no tag-based discovery, DI wiring stays readable
  top-to-bottom. `@internal FeatureLoaderInterface` lives in
  `polysource/filter` and is reused by the bridge (same precedent
  as `FeatureGate`).
- Inter-package constraints union `^0.1 → ^0.11` across all 16
  packages.

### Added

- **ADR-0032** — the FeatureLoader convention (gate placement,
  nullable-service pattern ownership, explicit listing, when NOT to
  split: single-feature packages keep their monolithic extension).
- `FeatureLoaderGateTest` per package — locks the `supports()`
  matrix: which bundle combination activates which feature
  (10 tests).

## [0.10.0] — 2026-08-06

**Host-app genericity + audit closure.** The EasyAdmin bridge becomes
properly themable, translatable, and CSP-friendly; the documentation
corpus is truth-synced against what actually ships; and the two large
refactors deferred from the v0.9.0 audit land, closing the audit.
Merged as #104 / #105 / #106.

### Added

- **Bridge theming via CSS variables.** The bridge's ~350 lines of
  inline CSS (index, subpanel mode, per-tab `:has()` pairing rules)
  move to a published stylesheet
  (`Resources/public/polysource-filter-bridge.css`, installed by
  `assets:install`). Every colour and key dimension flows through a
  `--polysource-*` custom property with `--bs-*` fallbacks — the
  bridge follows the host's Bootstrap 5.3 light/dark theme
  automatically, and hosts recolor with a 3-line override. New
  [theming guide](docs/user/easyadmin-filter-bridge/theming.md)
  documents the variables, the Twig override points, and the
  `@EasyAdmin` splicing trap.
- **Complete bridge i18n (5 → 57 keys, en + fr).** Every
  user-visible string renders through the translator: cell filter
  menu, column reorder, row density, quick filter, bulk scope,
  share link, toasts, the keyboard-shortcuts panel, and pluralized
  applied-filter counters. The 7 HTML-emitting Twig extensions take
  a nullable `TranslatorInterface` (translator-less hosts keep
  byte-identical English output). `CatalogCompletenessTest` locks
  key-set parity across locales.
- **`ScanPatternResolver`** unifies the four byte-identical
  `scanPattern()` copies in the Redis string/list/set/sorted-set
  data sources (with a full unit suite — three of the four copies
  had none) and drops a dead pre-v0.7 `instanceof` branch.
- **ADR-0031** retro-documents the operator-translation strategy
  shipped since v0.9: shared `InMemoryValueMatcher` for the
  in-memory adapter family, native per-dialect translation with
  documented silent degradation for the query-string family, and
  why the audit's proposed `OperatorTranslatorInterface` was
  rejected.
- **Coverage for the previously-untested bridge surface**:
  ColumnPreferenceController, FilterUrlTokenController,
  BundleRouteLoader, ColumnReorder/FilterShortUrl/FilterTree
  extensions, operator-routing tests for Flysystem and Messenger
  (+55 tests total across the release).

### Security

- **Open redirect closed in `FilterUrlTokenController`.** The
  `?index=` redirect-target guard only required a leading slash, so
  protocol-relative `//evil.example` (and the `/\` variant browsers
  normalise) passed it. Both now 404, with unit + integration
  regression coverage.

### Fixed

- **`TranslatableMessage` labels crashed the bridge.** Hosts
  labelling fields/filters with `t()` (EA 5) hit "Object of class
  TranslatableMessage could not be converted to string" in the
  column-visibility dropdown and the filter chips — both render
  through `|trans` now (a no-op for plain string labels).
- **Tab panes were invisible on browsers without `:has()`.** The
  pane-hiding default was not gated on `:has()` support, so the
  documented "every tab degrades to its own accordion" fallback
  actually hid every pane forever. The rules now sit under
  `@supports selector()`.
- **The 12-tab cap is real.** It was documented but never enforced;
  panes beyond 12 now stay always-visible (graceful) instead of
  depending on unbounded generated CSS.
- Both bridge demos 500'd on stale configuration: removed-in-v0.2
  Stimulus controllers in `controllers.json`, the removed
  `quick_ranges` form option, and the broken upstream
  doctrine/orm 3.6.8 (exact-version conflict now mirrored into the
  demos).

### Changed

- **CSP posture**: the bridge no longer emits static inline
  `<style>`/`<script>` — host policies don't need
  `'unsafe-inline'`, with one documented exception (the per-user
  hidden-columns block, which is request-dependent).
- `BulkScopeExtension` / `FilterShortUrlExtension` label parameters
  became `?string $label = null` (explicit host labels keep
  working; the defaults are now translated).
- `symfony/asset` promoted to an explicit bridge require (its
  templates call `asset()`; previously reached transitively).
- `FeatureGate` adoption completed across the bridge and the filter
  extension's AssetMapper prepend (audit task #67 predicate phase;
  the FeatureLoader class split stays scheduled for v0.11).
- Inter-package constraints union `^0.1 → ^0.10` across all 16
  packages.

### Docs

- Truth-sync sweep: root and bridge READMEs unfrozen from v0.5.7
  (including a false "PHP 8.4+/Symfony 7.4" compatibility claim —
  composer says `>=8.1` / `^5.4`), ROADMAP updated to real v0.10
  scope, the options and Stimulus controllers removed in v0.2.0
  purged from every page that still promoted them, ADR-018 addendum
  for the optional-version `#[AsPlugin]`, and five docs no longer
  recommend the `@!EasyAdmin` extends that silently disables the
  bridge on every index page.

## [0.9.1] — 2026-08-06

**Maintenance release** — dependency-freeze thaw, distribution
hygiene, and one latent-bug fix surfaced by the analyzer refresh.

### Fixed

- **bulk-async: the Mercure broadcaster was never registered.** The
  optional-dependency gate in `services.php` used `class_exists()` on
  `HubInterface` — an interface, for which `class_exists()` is always
  false — so `MercureBulkJobBroadcaster` was silently dropped even
  with `symfony/mercure` installed and consumers always fell back to
  Stimulus polling. Fixed with `interface_exists()`, plus the
  registration regression test that was missing. (Every other
  optional-dependency gate in the codebase was audited: this was the
  only faulty site.)
- **Inter-package constraints union every published lineage.** The
  constraints read `^0.1 || ^0.5 || ^0.7 || ^0.9` while every lineage
  0.1–0.9 is on Packagist; the gaps made mixed-lineage installs
  (e.g. bridge 0.9 alongside filter 0.8) unresolvable. Now
  `^0.1 → ^0.9` across all 16 packages.

### Changed

- **Every runtime dependency is declared explicitly.** A sweep of
  use-statements vs `composer.json` across all packages found deps
  that only resolved transitively: `easyadmin-filter-bridge` never
  declared `doctrine/orm`, `polysource/core` nor `twig/twig`;
  `audit` and `bulk-async` promote `polysource/symfony-bundle` from
  require-dev to require (their wiring references Bundle events and
  `ResourceRegistry` unconditionally); `psr/log` declared where
  type-hinted; `core` declares `composer-runtime-api`. Optional
  integrations gated behind `interface_exists()` are documented as
  `suggest` entries.
- **`#[AsPlugin]` versions derive from Composer metadata.** The
  attribute's `version` parameter is now optional; when omitted,
  `HasPluginMetadata` resolves the installed package version via
  `Composer\InstalledVersions`. All 15 bundles drop the hardcoded
  `0.1.0-alpha.1` (stale by eight minors);
  `polysource:plugins:list` now reports real versions.
- **Split-repo dists are lean.** Each package ships its own
  `.gitattributes` (the monorepo root one does not survive the
  split), export-ignoring `tests/` (~8k LOC for the bridge) and the
  JS test tooling. `assets/package.json` stays in the dist — it
  carries the `symfony.controllers` manifest, per the Symfony UX
  convention.

### Infrastructure

- CI unfrozen after a three-month freeze: showcase-demo voter gains
  the `?Vote` parameter (symfony/security-core 7.3+/8.0), the
  PHPStan and bridge-integration jobs are pinned to the Symfony LTS
  the matrix validates, and the upstream doctrine/orm 3.6.8
  SchemaTool regression (doctrine/orm#12547) is excluded via an
  exact-version conflict that self-heals on the next patch.
- All 42 open Dependabot alerts cleared: vitest raised to `^3.2.6`
  (both Stimulus test suites), showcase-demo lock refreshed in one
  grouped update (78 minor/patch bumps — Twig 3.28, Symfony 7.4.15,
  EasyAdmin 5.5.0, now exercised end-to-end by the Panther suite).

## [0.9.0] — 2026-05-18

**Architectural cleanup release** — full audit + 8 PRs landing
every CRITICAL/HIGH finding and the contained MEDIUM ones. Tracked
in `docs/maintainers/v0.9.0-architectural-cleanup.md` (17 of 22
audit items resolved; the 5 LARGE refactors — OperatorTranslator
across 6 adapters, DI extension split, audit subscriber decomp,
DoctrineDataSource decomp, Twig extension base class — are deferred
to v0.10 with documented rationale).

### Highlights

- **Security**: CSRF on all saved-view POST routes (3 scoped tokens),
  open-redirect closed (`SafeReferer`), XSS hardening on
  `RowDensityExtension`, `polysource_csrf_token` Twig helper for
  CSRF-less kernels, hardened operator passthrough.
- **Architecture**: `OperatorMap` + `FilterArrayExtractor` +
  `FilterUrlBuilder` + `FilterUrlParser` (single source of truth for
  the filter-URL vocabulary — kills the v0.8.1 regression class),
  `DoctrineMetadataHelper` (extracts the Doctrine 2.x/3.x cast
  bandaid), `IdentifiableInterface` (opt-in audit identity),
  `HealthCheckInterface` + registry (`DoctorCommand` is now a thin
  iterator over tagged checks — plugins extend the surface via
  `polysource.doctor.check` tag).
- **Polish**: LSP nullable return types tightened on 8 sites (8
  `phpstan-ignore` removed); two `Throwable` swallows narrowed
  (bundle boot, workflow resolver); search composer dep declared;
  inter-package version constraints normalized across 8 packages.

### Refactor (PR 7/7) — FilterUrlParser extraction + WorkflowResolver narrow

#### `Polysource\EasyAdminFilterBridge\Filter\FilterUrlParser`

Extracted from `SavedViewController::buildCriteria` — the previous
85-line static method with 9+ branches and 3-level nesting is now a
pipeline of small named helpers:

- `parseField()` — top-level entry per filter property
- `extractComparison()` — read EA `comparison` or Polysource `op`
- `promoteFromValues()` — Polysource `values[]` → `value`
- `promoteToBetween()` — Polysource `min`/`max` → `{min, max}` envelope
- `buildBetweenCriterion()` / `buildInCriterion()` / `mapComparison()`
  (the last delegates to `OperatorMap::fromEa()` from PR 4 — single
  source of truth for the operator alphabet)

`SavedViewController::buildCriteria()` becomes a thin static shim
over the new class so existing tests and host call-sites stay valid.

#### WorkflowResolver narrow

`WorkflowResolver::resolve()` caught `\Throwable` while looking up a
workflow in the registry. Narrowed to
`Symfony\Component\Workflow\Exception\InvalidArgumentException` +
SPL `\InvalidArgumentException`.

### Earlier PRs landed on main

- **PR 1/7** — CSRF on all saved-view POST routes (3 scoped tokens),
  open-redirect closed (`SafeReferer`), XSS hardening on
  `RowDensityExtension`, `polysource_csrf_token` Twig helper.
- **PR 2/7** — `search` composer dep + inter-package constraint
  normalization across 8 packages.
- **PR 3/7** — `DoctrineMetadataHelper` + `IdentifiableInterface` +
  data-driven chip dispatch.
- **PR 4/7** — `OperatorMap` + `FilterArrayExtractor` +
  `FilterUrlBuilder` (single source of truth for filter URL shapes).
- **PR 5/7** — LSP nullable return types tightened (8 sites);
  `Throwable` narrow in bundle boot.
- **PR 6/7** — `DoctorCommand` → `HealthCheck` registry.

### Validation

- 914 unit tests / 2141 assertions OK (+ FilterUrlParserTest)
- 15 integration tests / 55 assertions OK
- PHPStan max + CS clean

## [0.8.2] — 2026-05-17

**Dogfood signal #11 — cell-filter on EntityFilter columns.** `polysource_cell_filter_menu` rendered a chip for an EntityFilter column with the entity's display label (`__toString()`) as the URL filter value. EA's `EntityFilter` expects the entity primary key, so the filter applied but matched nothing — user saw "all rows" stay visible despite the chip.

### Added

`polysource_cell_filter_menu()` accepts a new optional `$filterValue` parameter:

```twig
{# Scalar column — display value is also the filter value (no change) #}
{{ polysource_cell_filter_menu('status', record.status) }}

{# Entity column — pass entity id explicitly so the URL filters by PK #}
{{ polysource_cell_filter_menu(
    'customer',
    record.customer.label,
    'Client',
    record.customer.id
) }}
```

Default behaviour unchanged: when `$filterValue` is null, falls back to `$value` (display) — preserves the existing scalar-column path.

### Validation

- 1019 tests / 2458 assertions OK (+2 from the new render/fallback tests)
- PHPStan max + CS clean

### Migration

Zero-effort for scalar columns. Hosts using cell-filter on entity columns gain the `$filterValue` parameter — adopt when ready.

## [0.8.1] — 2026-05-17

**Dogfood signal #10 — cell-filter URL shape mismatch.** The `polysource_cell_filter_menu()` Twig helper emitted `?filters[<prop>]=<value>` for the `eq` operator (scalar shorthand). The bridge's `UrlFilterApplier` honours this shape for filter-aware export / bulk dry-run, but EA's `crud/index` action filter pipeline does NOT — the chip rendered in the chips bar but no row filtering happened. Caught in dogfooding round 4.

### Fixed

`CellFilterMenuExtension::urlFor()` now always emits the **expanded** EA shape:

```diff
- ?filters[status]=paid                                  (scalar — ignored by EA index)
+ ?filters[status][comparison]==&filters[status][value]=paid    (expanded — applied)
```

Same for `neq` (already used expanded shape — unchanged). The scalar shorthand stays supported by the bridge's `UrlFilterApplier` for export / bulk consumers; only the cell-filter-menu URL builder is corrected.

### Validation

- 1017 tests / 2454 assertions OK (existing CellFilterMenuExtensionTest covers the change)
- PHPStan max + CS clean

### Migration

Zero-effort. Hosts using `polysource_cell_filter_menu(property, value)` get the corrected URL automatically.

## [0.8.0] — 2026-05-16

**`polysource/adapter-redis` reshape — 5 type-specific data sources.** Dogfood signal #8 surfaced a `WRONGTYPE` crash when SCAN returned keys of mixed Redis types — the v0.7 adapter only handled hashes. v0.8 reframes the Redis adapter to cover all 5 Redis data structures with type-pure semantics per data source.

### Added

#### 4 new type-specific DataSources

| Class | Type | Typical use |
|---|---|---|
| `RedisStringDataSource` (new) | string | Cache entries, sessions, simple feature toggles |
| `RedisListDataSource` (new) | list | Workflow queues, event streams, retry buffers |
| `RedisHashDataSource` (existing — type-narrowed) | hash | Feature flags, config objects |
| `RedisSetDataSource` (new) | set | Online users, blocked IPs, unique-tracking |
| `RedisSortedSetDataSource` (new) | sorted-set | Leaderboards, time-series buckets, priority queues |

Each DataSource:
- Implements `WritableDataSourceInterface`
- **Skips keys of the wrong type during SCAN** — no more `WRONGTYPE` crashes when a host's prefix accidentally overlaps multiple Redis types
- Uses type-coherent CRUD: `RedisListDataSource::create` pushes to the list, `RedisSortedSetDataSource::create` zadds with scores, etc.
- Exposes type-appropriate preview properties (`length`+`head` for lists, `cardinality`+`members` for sets, `topMembers` with scores for zsets, etc.)

#### 4 new Resource base classes

`RedisStringResource`, `RedisListResource`, `RedisSetResource`, `RedisSortedSetResource` — each a 5-property convenience over `AbstractResource`. Hosts subclass once per Redis namespace + type they want to admin.

#### Expanded client interface — `RedisClientInterface` (21 methods)

Old `RedisHashClientInterface` had 5 methods (hash + cross-type only). New `RedisClientInterface` covers all 5 Redis types:

- Cross-type (5): `scan`, `exists`, `del`, `type`, `ttl`
- String (2): `get`, `set` (with optional TTL)
- List (4): `llen`, `lrange`, `rpush`, `lpop`
- Hash (2): `hgetall`, `hmset` (unchanged from v0.7)
- Set (4): `smembers`, `sadd`, `srem`, `scard`
- Sorted set (4): `zrange` (WITHSCORES), `zadd`, `zrem`, `zcard`

#### `PredisRedisClient` + extended `InMemoryRedisClient` (test fake)

The Predis-backed implementation covers all 21 methods. The in-memory test fake (renamed from `InMemoryRedisHashClient` → `InMemoryRedisClient`) handles all 5 types with realistic semantics including SET overwriting any prior type at the same key, ZRANGE tie-breaking by lexicographic order, and SCAN iteration over the union of all keys.

### Deprecated (BC aliases retained — removed at v1.0)

- `RedisHashClientInterface` — now extends `RedisClientInterface` (the wider parent). Existing implementations keep working.
- `PredisRedisHashClient` — extends `PredisRedisClient` (empty subclass).
- `InMemoryRedisHashClient` (tests) — extends `InMemoryRedisClient` + `seed()` method forwards to `seedHash()`.

DI services: the bundle aliases the deprecated interface/class to the new ones so host code that injected `RedisHashClientInterface` keeps resolving.

### Why now

The dogfood install caught a `WRONGTYPE Operation against a key holding the wrong kind of value` crash on a Redis cache page after a workflow pushed list entries to keys matching the SCAN pattern. The v0.7 design assumed all keys under a prefix shared a type — false in real apps where multiple workflows touch overlapping namespaces.

The architectural call (covered in PR description): **5 type-specific resources, not 1 generic** — different types have semantically incompatible CRUD shapes (LRANGE has no equivalent for sets, HSET doesn't apply to strings). Bundling them is the right granularity.

A complementary `polysource/adapter-redis-console` package (read-only, type-aware, for "show me everything in Redis" debugging) is on the v0.9.x backlog as a separate concern.

### Migration

Zero-effort for hosts already using `RedisHashDataSource` + `RedisHashResource` — they keep working via the BC aliases.

New hosts wiring Redis admin pick the right DataSource per Redis namespace:

```php
#[AsResource]
final class WorkflowQueueResource extends RedisListResource
{
    public function __construct(RedisClientInterface $client) {
        parent::__construct(
            dataSource: new RedisListDataSource($client, 'queue:'),
            slug: 'workflow-queues',
            label: 'Workflow queues',
            permission: 'POLYSOURCE_QUEUE_VIEW',
        );
    }
}
```

## [0.7.3] — 2026-05-16

**Dogfood signal #7 — buttons inertes OOTB.** When the polysource standalone admin renders under its default `polysource/twig-theme` layout, the saved-views dropdown and the filters modal both rely on Bootstrap 5's data API (`data-bs-toggle="dropdown"`, `data-bs-toggle="modal"`). The layout shipped Bootstrap CSS via CDN since v0.1 but the matching Bootstrap JS bundle was absent — every Polysource page on a Stimulus-less host had inert controls.

### Changed (Layout — non-breaking)

#### `polysource/twig-theme/layout.html.twig` loads Bootstrap JS via CDN

Mirrors the existing Bootstrap CSS CDN pattern. Loaded as `<script defer>` before the `{% block javascripts %}` host block so hosts can attach their own listeners on top of Bootstrap's data API.

```diff
+ <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
+         crossorigin="anonymous" defer></script>
  {% block javascripts %}{% endblock %}
```

### Why this is OK as a patch release

- **No template changes** — existing markup with `data-bs-toggle` attributes already targets the Bootstrap data API, this fix just provides the JS that was always implied
- **No behavior change for hosts wrapping the layout** — hosts using `polysource.layout_template` config to extend their own chrome don't load this layout, so they're unaffected
- **No double-init risk for hosts with their own Bootstrap** — Bootstrap 5's data-bs-toggle initialisation is idempotent; loading the bundle twice doesn't bind listeners twice
- **CSP impact**: requires `script-src https://cdn.jsdelivr.net`. Hosts on stricter CSPs already needed the same exception for the CSS CDN; if they pin Bootstrap via host pipeline (Encore/AssetMapper), they don't need the CDN exception

### Architectural rationale

The senior call between (a) ship Bootstrap JS via CDN, (b) ship a vanilla JS polyfill of `data-bs-toggle`, (c) refactor templates to HTML-native `<details>` / `<dialog>`:

- (a) is what (b) would build less reliably (bootstrap 5 dropdown has ~600 LOC of edge-case handling we'd reinvent)
- (c) is the purest progressive-enhancement path but requires retemplating + restyling the entire admin shell (CSS classes are Bootstrap-shaped throughout)
- (a) is symmetric with the existing CSS CDN pattern, minimum blast radius, fixes OOTB UX

Path (c) stays on the v1.x backlog for a full HTML-native theme variant alongside the Bootstrap-themed one.

## [0.7.2] — 2026-05-15

**Hotfix — inter-package constraints.** v0.7.0/v0.7.1 shipped with inter-package `require` declarations only unioning `^0.1 || ^0.5`. Hosts pinning `polysource/symfony-bundle: ^0.7` got the bundle v0.7.1 but Composer kept `polysource/core` at v0.5.7 — leaving the 5 new concrete field types (`TextField`/`IdField`/…) inaccessible.

### Fixed

Union `^0.7` across all 14 inter-package require declarations:

```diff
- "polysource/core": "^0.1 || ^0.5"
+ "polysource/core": "^0.1 || ^0.5 || ^0.7"
```

No code change, just constraint widening. Reproduces exactly the `feedback_inter_package_constraints` rule from prior dogfooding — re-learned: every minor lineage tag must audit inter-package constraints before pushing.

## [0.7.1] — 2026-05-15

**Dogfood-driven fixes.** Two UX traps surfaced while wiring polysource standalone admin into a real multi-tenant app: a Resource declaring no fields renders rows-without-columns (worse than no rows at all), and the absence of any concrete `Field` class out of the box forces every host to write boilerplate before the first row even displays. Both fixed here.

### Added

#### 5 concrete field types in `polysource/core`

`TextField`, `IdField`, `BooleanField`, `DateTimeField`, `CodeField` — each a 5-line wrapper over `FieldTrait` that wires the matching `polysource/twig-theme` template (`@Polysource/field/{text,id,boolean,datetime,code}.html.twig`). Hosts no longer need to write their own field shim:

```php
public function configureFields(string $page): iterable
{
    yield IdField::new('id', 'ID');
    yield TextField::new('name', 'Name');
    yield BooleanField::new('active', 'Active');
    yield DateTimeField::new('createdAt', 'Created');
    yield CodeField::new('payload', 'Payload');
}
```

Core surface: 26 → 31 public types. Comfortable margin remains vs the ADR-010 cap (40).

#### Empty-fields fallback in IndexController + DetailController

When `ResourceInterface::configureFields()` returns `[]`, both controllers now synthesise a `FieldDto` per property key on a representative record (`page.items[0]` for index, the looked-up record for detail). Resources that declare no fields render a generic listing of their data instead of empty rows — the previous behaviour was a UX trap.

Synthesis policy:

- One field per `DataRecord::properties` key, property name doubles as label
- `DataRecord::$rawSource` is **never** surfaced (matches ADR-011 A3's `@internal` contract)
- Index: only synthesises if `page.items` is an array (one-shot Generators don't get the fallback — hosts who use cursor iterators must declare fields explicitly)
- Detail: always synthesises (the record is already materialised)

The synthesis helper is public + static: `ControllerSupport::synthesiseFieldsFromRecord(DataRecord $record): list<FieldDto>` — hosts can call it from custom controllers if they want the same fallback elsewhere.

### Why now

These were latent UX bugs since v0.1. They surfaced during a dogfood session where the new resource genuinely had nothing to declare yet — and the empty page hid the fact that the underlying SCAN was returning records correctly. Shipping fixes in v0.7.1 (patch, non-breaking) means hosts auto-get the better defaults without touching their code.

### Migration

Zero-effort upgrade. Hosts who had been working around the empty-fields trap with custom shim Field classes can:

1. Replace their shims with the new concrete types from `polysource/core`
2. Delete `configureFields()` entirely if the synthesised fallback suffices

Both are optional — existing field declarations keep working unchanged.

## [0.7.0] — 2026-05-15

**Pre-v1.0 freeze prep.** Closes the 6 remaining items from ADR-011
that gate the v1.0 API freeze. Two of them are breaking changes
(grouped here in v0.7.0); the other four were docs/docblock-only
and shipped alongside.

### Added

#### `FilterOperator` enum (closes ADR-011 item A4)

New `Polysource\Core\Query\FilterOperator: string` backing enum with 12
cases (`Eq`, `Neq`, `Gt`, `Gte`, `Lt`, `Lte`, `Like`, `In`, `Nin`,
`Between`, `IsNull`, `IsNotNull`). Replaces the free-form `string
$operator` on `FilterCriterion` — PHPStan / IDE now flag typos at
construct time, `match($operator)` blocks in adapters become exhaustive,
and the canonical operator set is self-documenting.

URL serialization preserved: `?filter[name][op]=...` round-trips through
`FilterOperator::tryFrom($string) ?? FilterOperator::Eq` at the boundary
(`AdminContextResolver`), so bookmarks with stale or unknown operators
silently fall back to `Eq` — same default as before.

The Twig API (`apply_filter_url(context, name, value, operator)`) keeps
its `string $operator` parameter for template ergonomics; conversion
happens at the resolver boundary, not in templates.

### Changed (BREAKING)

#### `FilterCriterion::$operator: string` → `FilterOperator` (closes A4)

`Polysource\Core\Query\FilterCriterion::$operator` is now typed as
`FilterOperator` instead of `string`. Application code constructing
criterions directly must update:

```diff
- new FilterCriterion('status', 'eq', 'active')
+ new FilterCriterion('status', FilterOperator::Eq, 'active')
```

`Polysource\Filter\Model\FilterCriterion` (the bridge-internal one used
by `polysource/easyadmin-filter-bridge` and the saved-view pipeline)
keeps `string $operator` intentionally — it accepts open-ended
Doctrine/EA comparison strings (`=`, `>=`, etc.) that an enum would
artificially close.

### Removed (BREAKING)

#### `BatchableDataSourceInterface` cut (closes ADR-011 item A1)

`Polysource\Core\DataSource\BatchableDataSourceInterface` removed from
`polysource/core`. Pure speculation since v0.1 — zero implementers
across the 6 shipped adapters, zero callers in the monorepo. Per
ADR-011 the criterion for keeping it was "an adapter v0.2 implements
`findMany()` AND a controller calls it to avoid a demonstrated N+1".
Neither happened in 6 months.

The core surface count drops from 26 → 25 public types (`ADR-010`
updated accordingly). The interface is reintroducible in v1.x if a
real cursor-batched adapter case emerges; the design space (HTTP
`?ids=1,2,3`, Meilisearch `getDocuments(filter: 'id IN [...]')`,
Doctrine `findBy(['id' => [...]])`) remains documented in the cookbook
for that future.

### Closed without code change

ADR-011 items A3, A6, A8, L4 — non-breaking docs/docblock-only
closures shipped in the same release cycle:

- **A3** `DataRecord::$rawSource: mixed` — `@internal` docblock added,
  property exempt from SemVer surface at v1.0
- **A6** `ResourceInterface::configureSearch()` — dangling reference
  removed from `docs/architecture/target-architecture.md` §6.2.1
- **A8** `AdminContext` decomposition — ADR-029 written documenting
  the 5-VO target structure for v1.x; code stays at 7 props
- **L4** `DataPage::isEmpty()` Generator one-shot — visible warning
  docblock with 3 safe call patterns

### Migration guide

For application code using `Polysource\Core\Query\FilterCriterion`:

1. Add `use Polysource\Core\Query\FilterOperator;` to every file
   constructing a `FilterCriterion`.
2. Replace string operator literals with enum cases:
   `'eq'` → `FilterOperator::Eq`, `'gt'` → `FilterOperator::Gt`, etc.
   The 12-case table is the canonical reference.
3. If your code reads `$criterion->operator` and uses it as a string
   (e.g., serialization, URL encoding), use `->value`:
   `$criterion->operator->value` returns `'eq'`, `'gt'`, etc.

For adapter authors:
- `match($criterion->operator)` blocks: replace string cases with
  enum cases (`'eq'` → `FilterOperator::Eq`, etc.).
- If you implemented `BatchableDataSourceInterface`, switch to extending
  `DataSourceInterface` directly and call `find()` in a loop until the
  interface is reintroduced (v1.x).

## [0.6.0] — 2026-05-15

**Three v0.6 backlog issues closed.** Two new features that consolidate the v0.5.7 dogfooding lessons into permanent tooling, plus a regression-coverage milestone for the bridge HTTP endpoints. Zero breaking change.

### Added

#### `polysource:doctor` console command (closes #29)

New `polysource:doctor` command in `polysource/symfony-bundle` runs
5 install-time / runtime checks and emits a PASS/WARN/FAIL table:

- PHP version (>= 8.1)
- Polysource bundles registered on this kernel
- EasyAdmin co-load (when bridge is loaded but EA isn't → WARN — the
  v0.5.7 C1 guard makes it safe, but the doctor still flags it as a
  configuration smell)
- Polysource plugins discovered
- Doctrine schema sync — uses `SchemaTool::getUpdateSchemaSql()` to
  compute pending DDL for polysource-namespaced entities; FAIL with
  a `migrations:diff` remediation hint when drift is detected (C3
  pattern from v0.5.7 dogfooding)

Exit code: 0 if all PASS/WARN, 1 if any FAIL. Suitable for CI /
pre-deploy gates.

Doctrine dep is `nullOnInvalid()` so the command works on hosts
without Doctrine (schema check degrades to WARN).

Doc: `docs/user/installation.md` "Verifying the install" section
extended with the command's sample output + exit-code contract.

#### Stimulus controllers advertised in `composer.json extra.symfony.controllers` (closes #30)

`polysource/filter` (`polysource--filter-chips`) and
`polysource/easyadmin-filter-bridge` (`polysource--filter`) now
declare their Stimulus controllers in `composer.json` so AssetMapper
+ `@symfony/stimulus-bundle` hosts auto-discover them — no need to
edit `assets/controllers.json` manually.

Webpack Encore + `@symfony/stimulus-bridge` hosts are unaffected
(`assets/package.json` declaration kept).

Filed as v0.1.1 dogfooding friction B3a, still open at v0.5.7, now
resolved.

### Tests

#### Bridge HTTP integration tests with TestKernel + SQLite (closes #31)

15 functional tests / 55 assertions exercising the bridge endpoints
end-to-end through a minimal Symfony kernel, an in-memory SQLite
EntityManager, and a 30-row `TestItem` fixture. Locks in regression
coverage for every v0.5.7 controller-layer fix that previously had
only unit coverage:

- C5 — `resolveEntityClass` on cold metadata cache (no 404 on first
  hit)
- C7 — `toIterable + HYDRATE_ARRAY + select('e')` yielding 0 rows
  in ORM 2.x (export CSV has the expected 30 data rows; matching-count
  `?samples=N` returns actual samples)
- C8 — DateTime exported as ISO 8601 / `DateTimeInterface::ATOM`
- C10 — `IN` / `NOT IN` / `IS [NOT] NULL` filter operators applied
  to DQL

Test infrastructure:
- `tests/Functional/Integration/App/TestKernel.php` — MicroKernel with
  FrameworkBundle + TwigBundle + SecurityBundle + DoctrineBundle +
  EasyAdminBundle + the 2 polysource bundles. SQLite in-memory ORM.
- `BridgeIntegrationTestCase` — boots a fresh kernel per test, drops
  + recreates schema, seeds fixtures, exposes a `request()` helper.
  Uses `$kernel->handle()` directly (not `KernelBrowser`) because
  Symfony's handle leaks an exception handler that
  `Kernel::shutdown()` doesn't unwind — PHPUnit 11's global
  `failOnRisky` would flag every test.

Tooling:
- New `integration` PHPUnit suite in `phpunit.xml.dist` (excluded
  from the `functional` suite to avoid double-coverage).
- New `make test-integration` target with `--do-not-fail-on-risky`.
- New `integration` CI job parallel to the PHPUnit matrix.

## [0.5.7] — 2026-05-15

**Ten install + runtime + UX fixes from dogfooding round 3.** Driving
the bridge through an existing client app (4 kernels, multi-tenant
`/{channel}/admin` EA mount, stale polysource schema, real-world
filter URLs) surfaced 10 distinct bugs in one afternoon. All fixed
with regression tests + docs in this single release.

| # | Severity | Subsystem | Symptom |
|---|---|---|---|
| C1 | DX | install / DI | Multi-kernel apps fail autowire on EA-less kernels |
| C2 | bug | install / routing | Multi-tenant `/{channel}/admin` hosts can't use auto-routes |
| C3 | bug | runtime / saved-views | Stale schema 500s every EA index page |
| C4 | UX | rendering / tabs | Filter tabs stacked vertically instead of horizontal strip |
| C5 | bug | controller / Doctrine | Valid mapped entities 404'd on cold metadata cache |
| C6 | bug | export / PHP 8.4 | `fputcsv()` deprecation on every CSV row |
| C7 | bug | export / Doctrine 2.x | `toIterable+HYDRATE_ARRAY+select('e')` yields 0 rows |
| C8 | bug | export / formatting | DateTime values exported as empty strings |
| C9 | bug | redirect / routing | Hardcoded `/admin` Referer fallback breaks multi-tenant |
| C10 | bug | filter / DQL | IN / NOT IN / IS [NOT] NULL silently dropped |

### Added — `polysource/easyadmin-filter-bridge`

#### Bundle config: `auto_register_routes` (C2)

New (and so far only) bundle config knob — opt-out for hosts that
mount EA under a custom URL prefix (typically multi-tenant apps
with `/{channel}/admin`). The bridge's controllers hard-code
`#[Route('/admin/...')]`; auto-importing them in a tenant-prefixed
host would splice routes OUTSIDE the tenant namespace, leaking
links out of channel scope.

```yaml
# config/packages/polysource_easyadmin_filter_bridge.yaml
polysource_easyadmin_filter_bridge:
    auto_register_routes: false
```

Then import the routes yourself, mounted wherever you need them:

```yaml
# config/routes/polysource.yaml
polysource_easyadmin_filter_bridge:
    resource: '@PolysourceEasyAdminFilterBridgeBundle/Resources/config/routes.php'
    type: php
    prefix: '/%channel%'   # or wherever EA lives in your host
```

Default `true` — zero-config single-tenant install (the common
case) is unchanged.

### Fixed — `polysource/filter`

#### Saved-view dropdown degrades on stale Doctrine schema (C3)

When the host has `polysource/filter` wired with Doctrine storage
but the underlying `saved_view` table is out-of-date (missing
table, missing column, read-only replica without DDL parity),
the bridge's auto-prepended `crud/index.html.twig` previously
500'd every EA index page because it calls `saved_views_dropdown()`
unconditionally and the DB query inside it propagated the
`SQLSTATE[42S22]: Column not found` error all the way up to the
template renderer.

`SavedViewExtension::renderDropdown()` and
`SavedViewExtension::activeSavedView()` now catch any `Throwable`
from the storage query and degrade silently — the dropdown
disappears, the host's pages keep working, and the host gets
the freedom to discover the migration gap on their own schedule.
Symmetric with the existing null-service guard.

Surfaced 2026-05-14 by dogfooding round 3: a host upgraded from
v0.1.x to v0.5.x without running polysource migrations hit
"Unknown column `p0_.column_widths_json`" on every admin page
the moment the v0.5 saved-view dropdown was rendered.

### Fixed — `polysource/easyadmin-filter-bridge`

#### Bridge is a no-op on EA-less kernels (C1)

Multi-kernel Symfony apps register the bundle globally via Flex
but only load EasyAdmin on one kernel. Previously, non-EA
kernels would fail to compile DI because `ChipValueFormatter`
type-hints EA's `AdminContextProviderInterface` unconditionally:

```
Cannot autowire service "Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter":
argument "$adminContextProvider" of method "__construct()"
references interface "EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProviderInterface"
but no such service exists.
```

Both `Extension::load()` and `Bundle::boot()` now short-circuit
when `EasyAdminBundle` isn't in `kernel.bundles`, and `prepend()`
skips splicing views into `@EasyAdmin`. The bundle is harmless
to register globally — services only appear where they can
actually wire.

### Documentation

- `docs/user/easyadmin-filter-bridge/getting-started.md` — new
  "Multi-kernel apps" + "Multi-tenant route prefixes" sections
  documenting both install patterns.

#### Filter tabs render as horizontal strip (C4 + C4-bis)

The bridge's `Polysource::tab(...)` markers organise filters into
tabs inside the modal/subpanel. With 4 tabs declared, the previous
template stacked each `<details>` vertically (block layout) or, after
a first fix attempt with `display: contents`, left the open tab's
pane squeezed to the left with siblings floating to its right (Safari
doesn't fully respect `display: contents` on `<details>`).

Final fix restructures the rendered HTML — strip and panes live in
two separate sibling containers. CSS `:has()` + `:nth-of-type` pair
the i-th tab[open] with the i-th pane. Both containers are plain
blocks; every browser agrees on the layout. Mutual exclusion stays
handled by `<details name="polysource-tab">` (zero JS). Graceful
degradation on browsers without `:has()` (pre-Safari 15.4, pre-Chrome
105) → every pane visible (= accordion fallback).

#### Entity-class resolution survives cold metadata cache (C5)

`MatchingCountController::resolveEntityClass()` and `ExportController`
combined `!hasMetadataFor && !isTransient` to detect non-mapped
entities. Inverted semantics: `hasMetadataFor` is true ONLY if
metadata has been LOADED in the current request — for a fresh
endpoint hit on a valid mapped entity whose metadata hasn't been
warmed yet, the condition fired and the endpoint 404'd. Fix:
just `if ($factory->isTransient($class)) throw` — `isTransient`
is the authoritative "this class is/isn't a Doctrine entity" probe
regardless of cache state.

#### `fputcsv()` PHP 8.4 deprecation (C6)

Pass an explicit `$escape = ''` argument to follow the PHP 8.4+
recommendation. Output is now RFC 4180 strict; PHP 9's eventual
default change can't affect us.

#### Export streaming yields zero rows on Doctrine ORM 2.x (C7)

`Exporter` and `MatchingCountController::buildSamplesQuery` used
`$qb->select('e')->getQuery()->toIterable()` with `HYDRATE_ARRAY`.
Doctrine ORM 2.x's ArrayHydrator can't stream full entities — the
iterator exits immediately. ORM 3.x relaxed this, but the bridge
supports both. Fix: select scalar fields individually so the
hydrator gets flat scalar rows.

Result: every export endpoint produced a CSV with only the header
row regardless of how many entities matched; matching-count's
`?samples=N` returned an empty list with the right `count`. Both
now stream correctly.

#### `Exporter::stringify()` handles DateTime (C8)

DateTime values previously fell through the stringify chain to the
empty-string default — every `createdAt`/`updatedAt` column came
out blank in exports. Added a `DateTimeInterface` branch returning
ISO 8601 / RFC 3339 (`DateTimeInterface::ATOM`): universal text-
sortable, opens correctly in Excel, parses cleanly with every
date library.

#### Post-action redirects no longer assume `/admin` mount (C9)

Three controllers (`ColumnPreferenceController`,
`ColumnOrderController`, `SavedViewController`) used
`$request->headers->get('Referer', '/admin')` as the post-action
redirect target. The hardcoded `/admin` 404s on multi-tenant hosts
(`/{channel}/admin`) and apps with a custom EA mount prefix. Browser
form submits — the standard call path — always have a Referer, so
the fallback rarely triggers. Fix: fall back to `/` (host root,
always valid) rather than a mount-specific path.

#### `UrlFilterApplier` handles IN / NOT IN / IS [NOT] NULL (C10)

The DQL applier's comparison `match` block previously only knew
scalar operators (`=`, `!=`, `<`, …). The bridge's own `InFilter`
(multi-select choice picker) submits `comparison=IN` + `value[]=…`;
the `NotNullFilter` (Any / Has value / Empty) submits
`comparison=IS NULL` or `IS NOT NULL` with no value. Both fell
through to default→null and were silently dropped — every IN /
NULL-state filter on URL was a no-op, queries returned ALL rows.

Fix: extended `match` to normalise `in`, `not in`, `not_in`,
`is null`, `is_null`, `null`, `is not null`, `is_not_null`,
`not_null` (all case-insensitively). Two new branches generate the
DQL: `IN` / `NOT IN` accepts an array value, `IS NULL` / `IS NOT
NULL` emits the bare predicate with no parameter.

### Documentation

- `docs/user/easyadmin-filter-bridge/getting-started.md` — new
  `2a. Multi-kernel apps`, `2b. Multi-tenant route prefixes`, and
  `2c. Database schema` sections. The schema section lists all 5
  tables polysource v0.5 expects, walks through `doctrine:migrations:diff`
  + `migrations:migrate`, and flags the MySQL implicit-commit-on-DDL
  gotcha that bit the dogfood host (`migrations:migrate` reports
  success but the transaction rolls back; workaround: direct
  `dbal:run-sql` per statement).

### Tests

7 new test classes / cases:
- `ConfigurationTest` (3) — bundle config tree, default, opt-out, type rejection.
- `PolysourceEasyAdminFilterBridgeBundleTest` (4) — `Bundle::boot()`
  route auto-import on EA kernel, non-EA kernel, opt-out flag, idempotency.
- `PrependFormThemeTest` (+1) — EA-absent no-op.
- `BridgeAutoConfigurationTest` (refactor) — setUp seeds `kernel.bundles`
  so existing autoconfig assertions hold under the new C1 guard.
- `SavedViewExtensionTest` (+2) — `Throwable` from storage degrades silently.
- `ExporterTest` (+1) — DateTime + DateTimeImmutable + non-UTC timezones
  format as ISO 8601.
- `UrlFilterApplierTest` (+4) — IN, NOT IN, IS NULL, IS NOT NULL DQL
  generation.

304 tests / 686 assertions GREEN. PHPStan: clean. CS: clean.

### Out of scope (deferred to v0.6)

- `polysource:doctor` console command — would detect schema drift,
  missing Stimulus pipeline, missing migrations and print actionable
  warnings.
- Symfony UX `extra.symfony.controllers` advertisement in the bridge's
  composer.json so AssetMapper auto-discovers the bridge's Stimulus
  controllers without manual `controllers.json` registration.
- Official `symfony/recipes-contrib` recipes for `polysource/filter`
  and `polysource/easyadmin-filter-bridge` (today both use Flex
  auto-generated recipes, no scaffolded `config/packages/*.yaml`).

## [0.5.6] — 2026-05-14

**Auto-loaded routes had no `_controller` default — every endpoint 404'd despite appearing in `debug:router`.** Continuation of dogfooding round 2 after v0.5.5 shipped.

### Fixed — `polysource/easyadmin-filter-bridge`

#### Auto-loaded routes wire to a real controller (PR #27)

v0.5.4's `BundleRouteLoader` used a bare `AttributeClassLoader` subclass with an empty `configureRoute()` body. Symfony's `AttributeClassLoader` is abstract — `_controller` is set by the concrete subclass's `configureRoute()`. Overriding it as a no-op wiped the only link between the route URL and its controller class.

`debug:router` showed the 8 polysource routes correctly, but `Defaults` was `NONE` for each. Every POST/GET 404'd at the ControllerResolver level.

Fix: swap the bare anonymous subclass for the framework-bundle's `Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader`, which correctly sets `_controller` to `ClassName::method`. One-line change in `BundleRouteLoader::loadAll()`.

### Tests

- `scripts/smoke-packagist-bridge.sh` phase 5 extended — also asserts every polysource route has a `Polysource\EasyAdminFilterBridge\Controller\…` `_controller` default. The future regression of this specific bug class is now caught.

## [0.5.5] — 2026-05-14

**Two more dogfood-round-2 frictions: export route greedy regex + kernel boot crash on missing dotenv + Bootstrap modal silently dead on backslashed DOM ids.**

### Fixed — `polysource/easyadmin-filter-bridge`

#### Export route regex no longer greedy on FQCNs (PR #26)

The `polysource_export` route pattern was `/admin/polysource/export/{resource}.{format}` with `resource: '[A-Za-z0-9_\\:.-]+'`. The `.` in the resource character class made the matcher greedy: it consumed `App\Entity\Item.csv` as a single `resource` segment, leaving nothing for `{format}`. Every export URL 404'd.

Fix: drop `.` from the resource character class. The matcher now splits cleanly at the last dot.

#### `Bundle::boot()` survives missing DEFAULT_URI env var

v0.5.4's `Bundle::boot()` auto-route-importer calls `$container->get('router')`. The Router service constructor reads `framework.router.default_uri` — typically resolved from `DEFAULT_URI` in the host's `.env`. Scripts that boot the kernel WITHOUT loading dotenv (`php -r`, broken CLI tools, some test harnesses) miss this env and `EnvNotFoundException` killed the entire kernel boot.

Fix: wrap router resolution in a try/catch. If it throws for any reason, skip the auto-import gracefully — the manual `routes.yaml` import remains the documented fallback.

### Fixed — `polysource/filter`

#### Saved-view modal DOM ids are CSS-selector-safe

The save-view modal's `id` and trigger's `data-bs-target` interpolated the resource_name (EA entity FQCN) directly: `#polysource-save-view-modal-App\Entity\Item`. Bootstrap resolves modal targets via `document.querySelector(targetAttribute)` — `\E…` is parsed as a CSS escape sequence. The selector became invalid, `querySelector` returned null, and Bootstrap silently never opened the modal.

Affected every host on every entity (all FQCNs have backslashes). Silent UI failure — button click did nothing.

Fix: slug `resource_name` via `replace({'\\': '-'})` before using it in DOM ids. `App\Entity\Item` → `App-Entity-Item`. Valid HTML5 id + valid CSS selector. Applied to `save_modal.html.twig` (4 id refs) and `dropdown.html.twig` (1 data-bs-target ref).

## [0.5.4] — 2026-05-14

**Route auto-import via `Bundle::boot()` — fresh installs no longer need a manual `routes.yaml` step + 3 dogfood-round-2 bugs.**

### Added — `polysource/easyadmin-filter-bridge`

#### `Bundle::boot()` auto-imports the 8 polysource routes (PR #25)

A fresh `composer require polysource/easyadmin-filter-bridge` left every host helper that generates a URL via the router (export, column-prefs, saved-view modal, filter-url-token, matching-count, column-order) silently broken — the documented manual import was easy to miss:

```yaml
# config/routes.yaml
polysource_easyadmin_filter_bridge:
    resource: '@PolysourceEasyAdminFilterBridge/config/routes.php'
    type: php
```

The new `BundleRouteLoader` walks the bridge's `#[Route]`-attributed controllers and splices the route collection into the host router at runtime via `Bundle::boot()`. Idempotent: probes `polysource_export` and short-circuits if the host has already imported.

Works on every Symfony version the bridge supports (5.4 LTS through 8.x) — `Bundle::boot()` is universal; `AbstractBundle::configureRoutes()` doesn't exist until Sf 7.5.

### Fixed — `polysource/easyadmin-filter-bridge`

#### Defensive `polysource_route_exists()` gate on the column-visibility dropdown

The bridge's auto-prepended `crud/index.html.twig` called `path('polysource_column_preferences_update', …)` at template render time. Without the routes (pre-v0.5.4 installs without the manual import), EVERY ADMIN INDEX PAGE 500'd with "Unable to generate a URL for the named route".

Fix: gate the dropdown markup on `polysource_route_exists('polysource_column_preferences_update')`. Same pattern as filter's saved-view dropdown already used for the default-toggle route. Belt-and-braces alongside the auto-import: if routes go missing for whatever reason, the page renders without the dropdown rather than 500.

#### `polysource_row_class()` accepts null entity

The v0.3.0 helper declared a non-nullable `object` parameter type, throwing `Argument #1 ($entity) must be of type object, null given` when templates passed a null entity (deleted row, soft-hidden, EA loading state, custom iteration with optional rows). Now returns the `$default` symmetric with the existing null-property branch.

### Tests

- `scripts/smoke-packagist-bridge.sh` extended to 6 phases — phase 5 asserts ≥ 8 polysource routes appear in `debug:router` on a fresh install. The auto-route regression is now guarded loud.

## [0.5.3] — 2026-05-14

**Critical packaging fix — fresh `composer require polysource/easyadmin-filter-bridge:^0.5` install resolved sibling packages to `v0.1.4` instead of `v0.5.x`.**

### Fixed — `polysource/*` (14 packages)

#### Inter-package constraints union both lineages (PR #24)

Every package declared its `polysource/*` dependencies with `"^0.1"`. After tagging v0.5.x across the monorepo, sibling packages had no clue they should resolve to v0.5.x — Composer pulled the latest `v0.1.x` of each dep.

User-facing symptom: every v0.3.0 / v0.4.0 / v0.5.0 / v0.5.1 / v0.5.2 host-side helper that depends on filter package services (ColumnPreferenceService, BulkActionHistoryService, RecentRecordsService, FilterUrlTokenService) was silently absent. Twig functions were undefined at render time; the DI gate quietly skipped controllers.

Fix: switch every inter-polysource constraint to `^0.1 || ^0.5` (union). Hosts staying on v0.1.x keep their old behaviour; hosts moving to v0.5.x get the matching minor of each dep resolved.

Touched 14 `composer.json` files. composer validate clean, CS clean.

### Process guard

New memory entry `feedback_inter_package_constraints.md` — when tagging a new minor lineage of polysource, audit + bump inter-polysource constraints BEFORE pushing the tag. Pre-flight check belongs in the release-pipeline checklist.

## [0.5.2] — 2026-05-14

**Backend feature UIs in the showcase.** Closes the gap left by
v0.5.1's tour stop 27 which claimed 4 backend features had "no
UI to capture". Each now has a concrete, visible UI in the
showcase + dedicated screenshot.

### Added — `examples/showcase-demo/`

#### Recently viewed records widget (v0.5.0 #6)

"Recently viewed orders" card on the home dashboard. Fetched via
a custom Twig function (`app_recently_viewed_orders(limit)`) at
render time to bypass the `Polysource\Widgets` registry's
boot-time caching of Dashboard instances (user-scoped widgets
can't go through the standard pipeline without forking the
bundle — documented limitation).

#### Bulk action history admin page (v0.5.0 #8)

Read-only EA `CrudController` on `polysource_bulk_action_history`.
Disables new/edit/delete to preserve the audit trail (append-only
contract). New `BulkActionHistoryStory` seeds 40 entries across
4 resources with realistic actor + metadata mix.

#### Matching count preview page (v0.5.0 #9)

`MatchingCountPreviewController` — server-rendered analogue of
the JSON `MatchingCountController` endpoint. Calls
`UrlFilterApplier` directly, renders count + 10 sample rows.
Linked from the Orders index Actions bar ("Preview bulk count").

#### Toast notifications (v0.5.0 #4)

Showcase EA `flash_messages.html.twig` override deferring
rendering entirely to `polysource_toasts()` (EA's stock template
otherwise consumes the bag first and the toast helper finds it
empty — documented mutual exclusivity). New
`/admin/showcase/toast-demo` debug route for deterministic
screenshot capture.

### Documentation

- `docs/user/showcase-tour.md` — stops 17-26 split into one
  dedicated stop per feature (was previously merged sections
  17-23 without embeds). Plus 4 new stops 27-30 for the
  backend-feature UIs built in this release.
- 30/30 screenshots green. 4 new captures (27-30) added.

## [0.5.1] — 2026-05-14

**Showcase + E2E + docs catch-up for v0.3-v0.5.** Closes the gap
flagged by the maintainer: none of the host-side helpers shipped
in v0.3, v0.4, v0.5 were wired into the showcase, and the docs /
screenshots / E2E coverage hadn't caught up.

### Added — `polysource/filter`

#### `polysource_active_saved_view(resourceName)` Twig function

Resolves the currently-active `SavedView` for a resource
(delegates to `SavedViewService::defaultFor()`, honours
`?view=<id>` then user / role defaults). Closes the v0.5.0 API
gap: `polysource_column_width_style()` needed a `SavedView` but
no template-side helper existed to fetch the active one.
Graceful null when the service isn't wired.

### Added — `examples/showcase-demo/`

#### Comprehensive showcase EA index override

`templates/bundles/EasyAdminBundle/crud/index.html.twig` —
single override demonstrating 14+ host-side helpers across v0.3,
v0.4, v0.5 in their canonical placement:

- `table_head` block — per-`<th>`: reorder ← → buttons (v0.5.0 #1),
  frozen sticky-left/right (v0.5.0 #2), saved-view column-width
  style (v0.5.0 #10). Second `<tr>` with per-column quick filter
  inputs (v0.4.0 #17).
- `table_body_row` block — `polysource_row_class()` colouring
  for Order status (v0.3.0 #14), frozen-left/right cells (v0.5.0
  #2), `polysource_cell_filter_menu()` on status/reference
  (v0.4.0 #16).
- `main` block — row density toggle (v0.5.0 #3), filter share
  button (v0.5.0 #7), toasts (v0.5.0 #4), kbd shortcuts cheat
  sheet (v0.5.0 #5). Bulk scope toggle inlined (v0.4.0 #19).

#### OrderCrudController v0.5.0 wiring

- Injects `RecentRecordsService`, `BulkActionHistoryService`,
  `ManagerRegistry` (all nullable for graceful degradation).
- `detail()` + `edit()` overrides call
  `RecentRecordsService::recordView()` — every order view
  upserts the user's MRU list (v0.5.0 #6).
- `configureActions()` adds Export CSV/XLSX actions (v0.3.0 #12,
  filter-aware since v0.5.0 #9) + bulk "Mark as cancelled"
  audit-trail demo (v0.5.0 #8).

### Added — E2E coverage

4 new Panther test files / 14+ scenarios:
- `V050PageLevelHelpersTest` — density toggle, toasts, kbd
  shortcuts, share button.
- `V050TableHelpersTest` — frozen columns, column reorder,
  quick filter row, row colouring, cell filter menu.
- `V050BackendIntegrationTest` — export actions, export
  endpoint, bulk scope toggle, recent records tracking.
- `V050ColumnVisibilityTest` — retroactive v0.3.0 coverage.

### Documentation + screenshots

- `docs/user/easyadmin-filter-bridge/whats-new.md` — adds
  "What v0.2.0 → v0.5.0 added on top" section indexing 25
  features grouped per version.
- `docs/user/showcase-tour.md` — 7 new tour stops (17-23).
- `README.md` — Status block updated to v0.5.0 with per-version
  highlights.
- **26 screenshots regenerated** (16 refreshed + 10 new for
  v0.3-v0.5 features). Pipeline fixes: `scrollIntoView` before
  click, `Kernel::prependPath` for showcase Twig precedence.

### Fixed (EA 5.x compatibility on showcase)

- `OrderCrudController::detail()` / `edit()` signatures —
  return `KeyValueStore|Response`.
- `Action::linkToRoute()` — pass array params (not Closure).
- Custom CRUD action `bulkMarkCancelled` decorated with
  `#[AdminRoute('/bulk-mark-cancelled', 'bulk_mark_cancelled')]`.
- Bulk scope toggle inlined in `main` (not the JS-driven
  `batch_actions` block which is hidden until row selection).
- Showcase template extends
  `@PolysourceEasyAdminFilterBridge/crud/index.html.twig`
  directly (NOT `@EasyAdmin/...` — that would resolve to itself
  via the new `prependPath` and PHP-infinite-recurse).

## [0.5.0] — 2026-05-14

**Simplification + polish sprint.** Ten new features across
`polysource/filter` and `polysource/easyadmin-filter-bridge` —
all server-side per ADR-027 progressive enhancement, all in
scope per ADR-028 (filter+listing UX layer for EA, NOT a
generic admin platform). Pays the technical debt left by v0.3.0
(unfiltered export) and v0.4.0 (bulk dry-run no count) up front,
then layers ten ergonomics features that match the listing UX
of modern admin tools (Looker, Metabase, Airtable).

Five new database tables (`polysource_bulk_action_history`,
`polysource_recent_records`, `polysource_filter_url_tokens`)
+ two column additions on existing tables
(`polysource_saved_views.column_widths_json`,
`polysource_column_preferences.column_order_json`). All
backward-compatible: new fields are nullable, pre-v0.5.0 rows
keep behaving like before. Showcase migrations
`Version20260515000001`-`000005` ship the schema; canonical
SQL + GDPR cleanup snippets documented per slice.

### Added — `polysource/easyadmin-filter-bridge`

#### Filter URL deep linking via short token (Task #7)

New `polysource/filter` slice — short shareable URLs for filter
slices. `FilterUrlToken` VO + `FilterUrlTokenService` +
Doctrine/InMemory storage. 12-hex-char tokens (`[a-f0-9]{12}`,
collision space 2^48) with retry-on-collision (capped at 8
attempts). New `polysource_filter_url_tokens` table.

Bridge layer:
- `FilterUrlTokenController` exposes
  `GET /admin/polysource/f/{token}` — looks up the slice,
  redirects to `index + ?filters[...]`. Open-redirect-safe
  (index path must start with `/`).
- `polysource_filter_short_url(resource)` Twig helper — returns
  the short URL for the current request's filter slice (empty
  when no filters).
- `polysource_filter_share_button(resource, label)` — pre-
  rendered Bootstrap button with `data-polysource-share-url`
  attribute for host-side clipboard wiring.

Showcase migration `Version20260515000005`; canonical SQL +
TTL pruning snippet documented in
`docs/user/easyadmin-filter-bridge/filter-deep-linking.md`.

#### Recently viewed records (Task #6)

New `polysource/filter` slice — per-user "most recently
viewed records" log. `RecentRecord` VO +
`RecentRecordsService` + Doctrine/InMemory storage. The
storage upserts by `(owner, resource, recordId)`: the same
record viewed N times yields one row with the latest
timestamp, not N rows. Anonymous users get a no-op.

New `polysource_recent_records` table with composite PK on
`(owner_id, resource_name, record_id)` + index on `viewed_at`.
Showcase migration `Version20260515000004` ships the schema;
canonical SQL + GDPR clean-up snippet documented.

Powers a "Recently viewed" widget on the resource index page
or a command palette MRU section. Hosts call
`$service->recordView(resource, recordId, label)` from their
detail/edit actions; render the list via
`$service->recentForCurrentUser(resource, limit)`.

#### Keyboard shortcuts help cheat sheet (Task #5)

`polysource_keyboard_shortcuts_help()` Twig helper — renders a
native HTML `<details>` cheat sheet listing the recommended
shortcuts (j/k navigate rows, `/` focus search, `f` open
filters, `?` toggle help, Esc close panels, etc.) along with
their scope. Server-rendered, keyboard-accessible by default.

`polysource_keyboard_shortcuts_list()` returns the canonical
list as `{key, label, scope}` triplets — useful for hosts who
render their own help UI or pass the list as JSON to a JS
controller.

The bundle deliberately does NOT ship a Stimulus controller —
per ADR-028, keyboard navigation overlaps with browser-level
accessibility (tab + enter) and hardcoding selectors for
"focused row" would conflict with host UX choices. A reference
Stimulus controller stub is documented for hosts who want
turnkey wiring.

See `docs/user/easyadmin-filter-bridge/keyboard-shortcuts.md`.

#### Bulk action history audit log (Task #8)

New `polysource/filter` slice — append-only audit log for
bulk actions. `BulkActionEntry` VO + `BulkActionHistoryService`
+ Doctrine/InMemory storage backends. Logs who ran what action
on how many rows of which resource, with optional free-form
metadata for action-specific payload. Per-user view
(`recentForCurrentUser`) for index widgets; admin view
(`recentForResource`) for all-users audit — caller gates the
latter behind their own admin firewall.

New `polysource_bulk_action_history` table with indexes on
`resource_name`, `owner_id`, `occurred_at`. Showcase migration
`Version20260515000003` ships the schema; canonical SQL
documented in `docs/user/filter/bulk-action-history.md`.

Rollback is **not** in scope for v0.5.0 — each action knows
how to undo itself in host-specific terms. Polysource preserves
the trail; hosts wire the rollback UI on top.

#### Column reordering (Task #1)

Per-user persistent column ordering for the EA index page.
Adds an optional `orderedColumns: ?list<string>` field to the
`ColumnPreference` VO (nullable for BC — pre-v0.5.0 rows
decode as "no override"). New `column_order_json` column on
`polysource_column_preferences`; migration documented in
`docs/user/easyadmin-filter-bridge/column-reorder.md`.

API:
- `ColumnPreferenceService::setColumnOrder()` — persist a new
  override (or `null` to clear).
- `ColumnPreferenceService::applyOrder()` — resolve effective
  order: override layered on top of host's defaults, with
  defaults not in the override appended at the end.
- `ColumnPreferenceService::orderedColumns()` — read the
  current override or null.

UI:
- `polysource_column_reorder_buttons(resource, property, columns)`
  Twig helper — renders ← → anchor pair per header.
- `ColumnOrderController` — GET
  `/admin/polysource/column-order/{resource}/move` endpoint
  with CSRF protection. Pure server-side baseline per ADR-027;
  hosts who want drag-and-drop layer their own Stimulus
  controller on the same persistence backend.

See `docs/user/easyadmin-filter-bridge/column-reorder.md`.

#### Column widths on saved views (Task #10)

The `SavedView` value object gains an optional
`columnWidths: array<string, int>` map — column property →
pixel width. Stored on a new nullable
`polysource_saved_views.column_widths_json` column (text,
JSON-encoded). Backward-compatible: pre-v0.5.0 rows decode as
an empty map. The model invariant rejects widths for unselected
columns or non-positive pixel values.

`polysource_column_width_style(view, property)` Twig helper
emits the `style="width: Xpx"` attribute for `<th>` or `<col>`
elements. `polysource_column_width(view, property)` returns the
raw int (or null) for hosts who need branching logic.

Hosts running production setups apply the v0.5.0
`ALTER TABLE polysource_saved_views ADD column_widths_json TEXT`
migration; see `docs/user/filter/saved-views.md`. Note: explicit
column **ordering** was already supported by the existing
`columns: list<string>` field (lists preserve order) — v0.5.0
formalises that in the docblock.

See `docs/user/easyadmin-filter-bridge/saved-column-configurations.md`.

#### Toast notifications (Task #4)

`polysource_toasts()` Twig helper. Renders Symfony flash
messages as Bootstrap `.alert` components positioned top-right
of the page (toast-like UX, alert markup so they render without
JS per ADR-027). Maps `success`/`error`/`danger`/`warning`/
`info`/`notice` flash types to the matching Bootstrap variants;
unknown types fall back to `alert-info`. XSS-safe: messages are
HTML-escaped. Auto-discovers EA's own bulk-action flashes — no
host integration required beyond dropping the helper call into
the layout. Optional host-side auto-dismiss via the
`polysource-toast` class hook.

See `docs/user/easyadmin-filter-bridge/toasts.md`.

#### Row density toggle (Task #3)

3 Twig helpers — `polysource_row_density_class()`,
`polysource_row_density_current()`,
`polysource_row_density_toggle()` — implementing a 2-state
compact/normal table density toggle. State lives in the URL
(`?density=X`); the toggle is a pair of anchor links — no JS,
no cookies. Compact uses Bootstrap's `table-sm`; normal stays
on the default `.table`. Query-parameter preservation: every
other slice (filters, sort, page) survives the toggle.

See `docs/user/easyadmin-filter-bridge/row-density.md`.

#### Frozen / sticky columns (Task #2)

`polysource_frozen_column(side, offset)` Twig helper. Pins a
table column to the left or right edge of its scroll container
via CSS `position: sticky`. Pure server-side, no JS, no external
stylesheet — the helper emits `class="..." style="..."`
attributes inline. Pinning falls back gracefully under strict CSP
(table renders without the freeze effect; rules can be lifted to
a stylesheet — documented). Stacking multiple frozen columns is
supported via the `offset` argument. Z-index sits at 2: above
table content, below modals and dropdowns.

See `docs/user/easyadmin-filter-bridge/frozen-columns.md`.

#### Filter-aware export + bulk dry-run count (Task #9)

`UrlFilterApplier` — a lean translator from the EA `?filters[...]`
URL slice to Doctrine `WHERE` clauses. Pays the technical debt
called out in the v0.3.0 and v0.4.0 CHANGELOGs (export was
unfiltered; bulk dry-run had a URL helper but no count endpoint).

- `ExportController` (GET `/admin/polysource/export/{resource}.{format}`)
  now applies the URL filter slice before streaming rows. CSV/XLSX
  exports launched from a filtered listing now export only the
  matching rows.
- `MatchingCountController` (GET `/admin/polysource/matching-count/{resource}`)
  — new JSON endpoint returning `{count, samples}` for bulk dry-run
  previews. Honours the same URL slice; `?samples=N` controls the
  preview size (capped at 50).

Supported filter shapes: scalar equality, expanded
`{value, comparison}` (`=`, `!=`, `<`, `<=`, `>`, `>=`, `between`,
`like`, `not like`), list-style multi-select (`IN`). Boolean
strings are coerced to PHP booleans. Properties not mapped on the
entity are silently dropped — no DQL injection risk.

Coverage limitations vs. EA's full QueryBuilder are documented;
hosts who need relation joins / FullTextSearch / custom filters
wire a custom EA Action and call the `Exporter` service directly
with EA's filter-aware QueryBuilder. See
`docs/user/easyadmin-filter-bridge/filter-aware-export.md`.

## [0.4.0] — 2026-05-14

**Tier 1 game-changers.** Five host-facing UX features inspired by
the listing ergonomics of modern admin tools (Looker, Metabase,
Airtable). All ship as composable Twig helpers — hosts integrate
in their own EA index template overrides; Polysource doesn't
prescribe layout. Server-rendered first per ADR-027.

### Added

#### Filter from cell value (Task #16)

`polysource_cell_filter_menu(property, value, label)` Twig helper.
Renders a Bootstrap dropdown next to a cell with three actions:

- "Filter where {label} = this" → adds `?filters[X]=value`
- "Exclude {label} = this" → `?filters[X][comparison]=!=&[value]=...`
- "Show only this {label}" → replaces query, keeping only this slice

Each item is a plain `<a href>` — pure server-side, no JS for the
feature to work. Companion helper `polysource_cell_filter_url(...)`
exposes the URL builder for hosts that want custom UI.

Stringification: scalars, bools (0/1), `BackedEnum` (`->value`),
`UnitEnum` (`->name`), Stringable. Empty values produce no menu.

#### Per-column quick filter row (Task #17)

`polysource_quick_filter_row(property, placeholder)` Twig helper.
Renders a small `<form method="GET">` per column header containing
an `<input name="filters[X]">` + hidden inputs preserving every
other query param (other filter slices, sort, page). User types +
Enter submits → page reloads with the filter slice applied.

Hidden-input dance preserves nested filter shapes
(`filters[X][value]`, list-style multi-select).

#### Saved column configurations / "perspective" (Task #18)

Documentation-only — `SavedView::columnsJson` (shipped in v0.1.0)
already persists the user's column selection alongside filters +
sort. The combined save = the perspective concept. New doc explains
the wiring pattern + why no parallel "PolysourceColumnConfig"
entity (ADR-028 scope discipline).

#### Cross-page selection + bulk dry-run (Task #19)

Three Twig helpers shaping the UX vocabulary:

- `polysource_bulk_scope_toggle(label)` — checkbox switching the
  bulk action from "selected rows on this page" to "all rows
  matching the current filter slice". Submits via `name="bulk_scope"`.
- `polysource_bulk_scope_active()` — true when the request carries
  the flag (use for `checked` state + controller branching).
- `polysource_bulk_dry_run_url(actionUrl)` — appends `?dry_run=1`.
  Endpoint contract: returns JSON `{count, samples}` instead of
  executing.

Polysource doesn't ship the count logic (filter-aware QueryBuilder
integration deferred to v0.5+). Hosts implement the dry-run
endpoint per resource.

#### Empty state design system (Task #20)

Three Twig helpers for contextual empty-state UX when a filtered
listing yields zero results:

- `polysource_has_active_filters()` — distinguishes "no results
  match your filters" (true) from "no data yet" (false).
- `polysource_clear_filters_url()` — current page URL with every
  `filters[...]` / `filter[...]` key stripped (CTA href).
- `polysource_active_filters_summary()` — flat `list<{property, value}>`
  description of the applied slice. Handles three URL shapes
  (scalar, expanded `{value, comparison}`, list/multi-select).

Polysource ships no opinionated layout — hosts compose the message
+ CTAs themselves in their own EA empty-row override.

### Known limitations / deferred to v0.5+

- **Filter-aware export count.** Both the v0.3.0 export endpoint
  and the v0.4.0 bulk-scope dry-run helper are unfiltered. Hosts
  who need filter-aware counting / export today override the
  controllers and apply the filter slice themselves on the
  QueryBuilder. A generic integration with EA's filter-aware
  QueryBuilder is on the v0.5+ roadmap.

- **Column width + explicit ordering** on saved column configs.
  Current `columns_json` is a flat ordered list; per-column width
  + reordering independent of declaration order land in v0.5+.

### Migration

No host-side wiring beyond loading the bridge routes (same as
v0.3.0). The new helpers are all Twig-only — hosts opt in by
calling them in their templates. No new entities, no migrations.

### ADRs

Continues to honour [ADR-027](docs/adr/0027-progressive-enhancement.md)
(every interactive feature has a server-side baseline) and
[ADR-028](docs/adr/0028-scope-discipline.md) (Polysource is the
filter+listing+detail-page UX layer).

## [0.3.0] — 2026-05-14

**The 4 originals.** Four host-facing features hosts can drop in on
top of an existing EA bridge install:

1. **Column visibility toggle** — per-user, persisted server-side.
2. **Default saved view per user** — flag one of your own saved views
   as your personal default; clean URLs auto-apply it.
3. **Row conditional styles** — Twig helper mapping a property value
   to a CSS class on the table row.
4. **Export current view (CSV / XLSX)** — streaming export endpoint.
   Unfiltered baseline (filter-awareness on the v0.4.0 roadmap).

All features are server-rendered first per ADR-027 (progressive
enhancement). No new Stimulus controllers shipped by Polysource.

### Added

#### Column visibility toggle (Task #11)

- `polysource/filter::ColumnPreference\Model\ColumnPreference` —
  immutable VO carrying (ownerId, resourceName, hiddenColumns).

- `ColumnPreferenceStorageInterface` + `DoctrineColumnPreferenceStorage`
  + `InMemoryColumnPreferenceStorage` — VO↔record pattern mirroring
  SavedView. Composite primary key (owner_id, resource_name).

- `ColumnPreferenceService` — TokenStorage-resolved owner; silently
  no-ops for anonymous users.

- `ColumnPreferenceExtension` (Twig) — `polysource_column_hidden(...)`
  + `polysource_hidden_columns(...)`. Safe defaults for anonymous
  users and unwired storage.

- `polysource/easyadmin-filter-bridge::ColumnPreferenceController`
  — POST `/admin/polysource/column-preferences/{resource}` with CSRF.

- EA index template integration: toolbar dropdown + server-rendered
  `[data-column="X"] { display: none }` CSS for each hidden column.

- Docs: `docs/user/filter/column-preferences.md` (migration SQL +
  UX flow).

#### Default saved view per user (Task #13)

- `SavedView::withDefault(bool)` immutable updater.

- Constructor invariant relaxed: `isDefault=true` no longer requires
  `roleAsDefault`. New semantic — *personal* default
  (`roleAsDefault === null`) vs *role* default
  (`roleAsDefault !== null`). The reverse invariant
  (`roleAsDefault` without `isDefault`) stays rejected.

- `SavedViewService::markAsDefault()` / `unmarkAsDefault()` —
  owner-EDIT-protected. `mark` enforces exclusivity (clears the
  flag on every other personal-default view of the same user for
  the same resource); role defaults left alone.

- `SavedViewService::defaultFor()` now returns the personal default
  on a clean URL (no `?view=` or `?filters[...]`) before falling
  back to the role default.

- `SavedViewController::toggleDefault` — POST
  `/admin/saved-views/{id}/default`. Dropdown template renders a
  ★/☆ button for owner-owned non-role-default views; hidden if the
  route isn't registered.

- Translations `polysource.saved_views.default.{mark,unmark}` (en + fr).

#### Row conditional styles (Task #14)

- `polysource/easyadmin-filter-bridge::Twig\Extension\RowClassExtension`
  exposing `polysource_row_class(entity, property, classMap, default)`.
  Resolves via reflection (try `getX()`, `isX()`, public property).
  Handles scalars, bools, `BackedEnum` (`->value`), `UnitEnum`
  (`->name`).

- Docs: `docs/user/easyadmin-filter-bridge/row-styles.md`.

#### Streaming export (Task #12, v0.3.0 baseline)

- `Polysource\EasyAdminFilterBridge\Export\Exporter` — stateless
  service. `streamCsv()` + `streamXlsx()` return `StreamedResponse`.
  Memory-bounded.

- `ExportController` — GET `/admin/polysource/export/{resource}.{format}`.
  Doctrine `toIterable()` in array-hydration mode.

- CSV: PHP built-in `fputcsv`, no external dep, UTF-8 BOM.

- XLSX: requires `openspout/openspout` ^4.0 (declared as `suggest`).
  Throws actionable `RuntimeException` when missing.

- Value coercion + filename sanitisation (CR/LF/quote/null stripped).

- Docs: `docs/user/easyadmin-filter-bridge/export.md`.

### Known limitations

- **Export is unfiltered in v0.3.0.** Every row of the resource is
  exported; URL `?filters[...]` slice NOT applied. Filter-aware
  export is on the v0.4.0 roadmap. Hosts who need it today override
  `ExportController` and apply the filter slice themselves.

### Migration

For hosts upgrading from `0.2.0` to `0.3.0`:

| Feature | Wiring |
|---|---|
| Column visibility | Run the migration in `docs/user/filter/column-preferences.md` (creates the `polysource_column_preferences` table). |
| Default saved view | No new wiring — toggle appears automatically on owner-owned views in the dropdown. |
| Row conditional styles | Optional. Host overrides the EA index template to call `polysource_row_class(...)`. |
| Export CSV | No new wiring beyond loading the bridge routes (same resource as saved-views + column-preferences). |
| Export XLSX | `composer require openspout/openspout` |

If you've serialised `SavedView` objects with `isDefault=true` and
no `roleAsDefault` previously, those would have been rejected by
the old invariant — no migration friction for existing data.

### ADRs

Continues to honour [ADR-027](docs/adr/0027-progressive-enhancement.md)
(every interactive feature has a server-side baseline) and
[ADR-028](docs/adr/0028-scope-discipline.md) (Polysource is the
filter+listing+detail-page UX layer).

## [0.2.0] — 2026-05-14

**Simplification + progressive enhancement.** Five bridge features
that required Stimulus to function were either removed (because they
duplicated an EA-native capability or fell outside the project's
scope) or rewritten as native HTML (`<details name="...">`) that
works without any JavaScript. The 5 dependencies on Stimulus that
hosts had to provide before bridge UIs rendered correctly are down
to one (chip × removal — slated for the same treatment in v0.3.0).

Two ADRs were ratified before this release to ground every decision:
[ADR-027 — Progressive enhancement](docs/adr/0027-progressive-enhancement.md)
("every interactive feature MUST have a server-side baseline; JS is
enhancement, never a precondition") and
[ADR-028 — Scope discipline](docs/adr/0028-scope-discipline.md)
("Polysource is the filter+listing+detail-page UX layer for
EasyAdmin, not an admin platform alternative").

### Breaking changes

- **`polysource/easyadmin-filter-bridge`** — three FormType options
  removed:
  - `presets` and `show_clear` on `EnhancedDateTimeFilterType`
  - `quick_ranges` on `EnhancedNumericFilterType`

  All three drove Stimulus-rendered preset buttons. Hosts without a
  Stimulus pipeline saw inert UI (visible-but-non-functional
  buttons) — a hard violation of [ADR-027](docs/adr/0027-progressive-enhancement.md).
  Native HTML5 date pickers + EA's built-in Reset cover the same
  ergonomics. Hosts who relied on these options must drop the
  `setFormTypeOption('presets', …)` / `('show_clear', …)` /
  `('quick_ranges', …)` calls — Symfony's OptionsResolver will
  throw `UndefinedOptionsException` otherwise. No silent
  back-compatibility shim.

- **`polysource/filter`** — two Stimulus controllers removed:
  - `polysource--filter-modal-layout` (306 lines + 280-line vitest
    suite) — used to reorganise EA's AJAX-loaded filter form into
    tabs and group accordions client-side. The same structure is
    now rendered server-side by the bridge's new
    `crud/filters.html.twig` override using native
    `<details name="polysource-tab">` (HTML Living Standard,
    Chrome 120 / Safari 17.2 / Firefox 121 — Dec 2023+).
  - `polysource--filter-subpanel` (111 lines + 111-line vitest
    suite) — used to toggle a `show` class on the standalone-filter
    subpanel + a body class + ESC handler + tab switching. The
    standalone subpanel template is rewritten around a native
    `<details>` element. Hosts that need ESC-to-close,
    click-outside-to-close, or focus-trap behaviour can write a
    small enhancement controller of their own.

### Removed

- `polysource/easyadmin-filter-bridge::EnhancedDateTimeFilterType`:
  the 5 `PRESET_*` constants, `DEFAULT_PRESETS`, and the entire
  options machinery for `presets` + `show_clear`. The class is now
  a thin block-prefix override of EA's `DateTimeFilterType`. The
  `DateTimeFilterEnhancer` configurator is unchanged in behaviour
  (still swaps the form type) but no longer mentions the dropped
  options in its docblock.

- `polysource/easyadmin-filter-bridge::EnhancedNumericFilterType`:
  the `quick_ranges` option and its array-of-ranges normalizer.
  `step` (numeric granularity hint) is retained.

- `polysource/easyadmin-filter-bridge::polysource_filter_controller.js`:
  the three Stimulus action methods `applyPreset` (with its 7-preset
  date arithmetic), `applyQuickRange`, and `clearValues` plus their
  private helpers (`#computePresetRange`, `#startOfDay`,
  `#formatDateForInput`, `#queryInputs`, `#setSelectValue`,
  `#setInputValue`). The class survives as a value-only Stimulus
  controller so hosts who extend it from their own JS layer still
  get the typed `data-polysource--filter-*-value` parsing. Full
  controller deletion is deferred to v0.2.1 (it would cascade into
  the form theme template and the functional Twig render tests).

- Translations: `polysource.filter.preset.*` (8 keys),
  `polysource.filter.presets.label`, `polysource.filter.quick_ranges.label`,
  `polysource.filter.clear` from
  `PolysourceEasyAdminFilterBridge.{en,fr}.yaml`.
  `polysource.filter.cancel` from `PolysourceFilter.{en,fr}.yaml`
  (the subpanel no longer has a Cancel button — closing is done via
  the `<details>` summary toggle).

- `polysource/easyadmin-filter-bridge::FilterTreeExtension`: the
  Twig function `polysource_filter_tree(...)` now returns the
  structured array directly (was JSON-encoded for the deleted
  client-side controller). The internal method `renderTree` was
  renamed `buildTree` to match the new return shape.

### Changed

- The bridge now overrides EA's `@EasyAdmin/crud/filters.html.twig`
  to server-render the filter form into `<details name="polysource-tab">`
  tabs + `<details open>` group accordions based on
  `Polysource::tab(...)` / `Polysource::group(...)` markers. The
  modal shell template (`crud/includes/_filters_modal.html.twig`)
  is now identical to upstream — the `data-controller` and JSON
  tree data attribute are gone.

- `index.html.twig` CSS for tabs: ~80 lines of `.nav.nav-tabs`
  Bootstrap-styling removed, replaced by ~50 lines of
  `details.polysource-filter-tab` styling. Same visual outcome
  (EA-style underlined tabs); no `.nav-tabs` JS dependency.

- The standalone-filter `subpanel.html.twig` is rewritten around
  `<details>` for the slide-in container + `<details name="...">`
  for inner tabs. Inline `<style>` block in the same template
  drives the slide animation (`transform: translateX(100%)` →
  `translateX(0)` triggered by the `[open]` attribute).
  ~90 lines of Bootstrap offcanvas markup + Stimulus targets replaced
  by ~80 lines of native semantic HTML + CSS.

### Migration guide

For hosts upgrading from `0.1.4` to `0.2.0`:

| If you have… | Do this |
|---|---|
| `->setFormTypeOption('presets', [...])` on a `DateTimeFilter` | Remove the call. Native HTML5 date picker covers the UX. |
| `->setFormTypeOption('show_clear', true)` on a `DateTimeFilter` | Remove the call. EA's Reset button is the replacement. |
| `->setFormTypeOption('quick_ranges', [...])` on a `NumericFilter` | Remove the call. If range shortcuts are critical for that CRUD, add `<button type="submit" formaction="?filter[X]=...">` in a custom CRUD template. |
| Code referencing `EnhancedDateTimeFilterType::PRESET_*` constants | Remove the references. Constants no longer exist. |
| Twig templates calling `polysource_filter_tree(filtersConfig)` and expecting a JSON string | Adjust — the function returns the array now. The bridge no longer needs callers to JSON-encode the tree (the in-template iteration consumes the array directly). |
| Custom JS extending `polysource--filter` and calling `applyPreset` / `applyQuickRange` / `clearValues` | Remove. These actions don't exist anymore. |
| Custom JS extending `polysource--filter-modal-layout` or `polysource--filter-subpanel` | These controllers are deleted. Layout is now server-rendered — there's nothing to extend. Tab/group rearrangement happens in `crud/filters.html.twig` via Twig overrides. |
| `polysource.filter.preset.*` / `polysource.filter.cancel` / `polysource.filter.presets.label` / `polysource.filter.quick_ranges.label` / `polysource.filter.clear` translation overrides | Remove the keys from your host translations. They're no longer looked up. |

For hosts that **don't** have Stimulus installed, this release is
strictly an improvement — tabs, groups, and the subpanel now work
out of the box where before they rendered as inert or flat layouts.
For hosts **with** Stimulus, the UX is the same minus three nice-
to-haves (preset buttons, quick-range buttons, per-tab applied-filter
count badges) and minus the slide-animation polish on the subpanel
(native `<details>` is synchronous; no fade-in/out). All four can
return as v0.3+ progressive-enhancement layers if real usage
warrants them.

### ADRs

- [ADR-027 — Progressive enhancement](docs/adr/0027-progressive-enhancement.md): every interactive feature MUST have a server-side baseline.
- [ADR-028 — Scope discipline](docs/adr/0028-scope-discipline.md): Polysource is the filter+listing+detail-page UX layer for EasyAdmin, not an admin platform alternative.

## [0.1.4] — 2026-05-13

**Architectural fix — `saved_views_dropdown` ownership moves to
where the data model lives.** The v0.1.2 install-blocker fix was a
band-aid on a real architectural defect: the
`saved_views_dropdown()` Twig function was owned by
`polysource/symfony-bundle::PolysourceFilterExtension`, even though
the SavedView entity, service, voter, and storage adapter all live
in `polysource/filter`. Bridge-alone installs (the documented
happy path for `polysource/easyadmin-filter-bridge` users) didn't
pull symfony-bundle, so the function wasn't registered — hence
v0.1.1's "Unknown saved_views_dropdown function" crash.

v0.1.2 worked around the symptom by adding a stub registration in
`polysource/easyadmin-filter-bridge::ChipExtension` (guarded by
a runtime gate `polysource_saved_views_available()`). v0.1.4
**relocates the function to its rightful owner**:

- `polysource/filter::SavedViewExtension` now registers
  `saved_views_dropdown` directly.
- `polysource/symfony-bundle::PolysourceFilterExtension` no longer
  registers `saved_views_dropdown` (and no longer needs an
  optional `?SavedViewExtension $savedViewExtension` constructor
  argument).
- `polysource/symfony-bundle` adds `polysource/filter: ^0.1` as a
  hard `require` — saved-views is a core admin-engine feature, not
  an optional plugin.
- `polysource/easyadmin-filter-bridge::ChipExtension` drops the
  v0.1.2 stub for `saved_views_dropdown` and removes the
  `polysource_saved_views_available()` gate function entirely.
  The bridge's `crud/index.html.twig` no longer gates the call —
  the function is always registered via the transitive
  `polysource/filter` dep.

Result: hosts using either `polysource/easyadmin-filter-bridge`
alone OR `polysource/symfony-bundle` get a working
`saved_views_dropdown()` out of the box. The runtime function
returns an empty string when the host hasn't wired a
`SavedViewStorageInterface` (no DoctrineBundle or no
SecurityBundle present) — graceful degradation without crashes.

### Fixed

- `polysource/easyadmin-filter-bridge::ChipExtension` no longer
  needs the v0.1.2 stub / gate pattern (the band-aid layer is
  gone). Adds a regression-guard test ensuring `ChipExtension`
  exposes only `polysource_chip_value` and never re-registers
  `saved_views_dropdown` or `polysource_saved_views_available`.

- `polysource/filter::SavedViewExtension` registers
  `saved_views_dropdown` with graceful degradation: nullable
  `SavedViewService` and `Twig\Environment` in the constructor,
  the Twig function returns an empty string when either is unwired.

- `polysource/filter::DependencyInjection::PolysourceFilterExtension`
  gates SavedView storage on `DoctrineBundle` AND `SecurityBundle`
  being loaded (not only on `EntityManagerInterface` class existing
  — the class is loadable in test deps without the bundle, which
  used to crash DI compilation in tests that boot a minimal
  kernel). Registers `SavedViewExtension` unconditionally when
  `TwigBundle` is loaded; full deps are autowired when storage is
  wired, otherwise constructed with all-null arguments.

### Changed

- `polysource/symfony-bundle` requires `polysource/filter: ^0.1`.
  Existing v0.1.3 hosts already pull `polysource/filter` transitively
  via the bridge or via `polysource/audit`; making the dep explicit
  reflects what the bundle actually needs.

- `polysource/symfony-bundle::PolysourceFilterExtension::savedViewsSupported()`
  now returns `true` unconditionally. Kept as a backward-compat
  helper for v0.1.x templates that gate on it; new templates can
  drop the gate entirely.

- `polysource/twig-theme::templates/index.html.twig` drops the
  `{% if polysource_saved_views_supported() %}` gate around the
  `saved_views_dropdown` call. The function is now always
  registered when the template renders.

### Migration notes

- Hosts on v0.1.3 with the bridge alone: upgrade is transparent —
  the function is now real (not a stub), so saved views actually
  render when a storage adapter is wired.
- Hosts on v0.1.3 with `polysource/symfony-bundle`: upgrade pulls
  `polysource/filter` explicitly. No behaviour change for hosts
  that already had filter installed.
- Hosts that override `polysource/twig-theme::templates/index.html.twig`
  can drop the `polysource_saved_views_supported()` gate at their
  convenience — the gate still works (always-true) so the template
  parses, but it's no longer load-bearing.

## [0.1.3] — 2026-05-12

**Documentation correction.** The v0.1.2 release notes added a
"Known limitations" entry stating that Stimulus controller
auto-discovery via `extra.symfony.controllers` was not shipped and
required a future v0.2 refactor. **That claim was wrong on two
counts** and is retracted in this release:

1. `composer.json extra.symfony.controllers` is **not a Symfony UX
   convention**. The canonical Stimulus controller manifest lives in
   each package's `assets/package.json symfony.controllers` — which
   Polysource has shipped from v0.1.0 onwards for all four packages
   with controllers (`polysource/filter`,
   `polysource/easyadmin-filter-bridge`, `polysource/bulk-async`,
   `polysource/search`).
2. The custom-identifier story Polysource uses
   (`polysource--filter`, `polysource--filter-modal-layout`, etc.,
   with a single dash between segments rather than the auto-generated
   double-dash `vendor--package--key` form) is preserved end-to-end
   by the `name` field on each manifest entry. `@symfony/stimulus-bundle`
   honors this `name` (cf. `AssetMapper/ControllersMapGenerator.php`)
   so AssetMapper hosts get the same identifiers as Webpack Encore
   hosts.

**What actually works today** (and did from v0.1.0):

- **Webpack Encore + `@symfony/stimulus-bridge`**: auto-discovery
  zero-config on `composer require`.
- **AssetMapper + `@symfony/stimulus-bundle`**: auto-discovery via
  one manual `assets/controllers.json` snippet per host. A Symfony
  Flex recipe (tracked separately in `symfony/recipes-contrib`)
  would make this fully zero-config.

The v0.1.2 docs have been corrected to describe both paths
accurately and drop the "scheduled for v0.2" claim.

### Docs

- Rewrote the "JavaScript / Stimulus prerequisite" section in
  `docs/user/installation.md`,
  `docs/user/easyadmin-filter-bridge/getting-started.md`, and
  `docs/user/filter/getting-started.md`. Replaced the inaccurate
  "manual registration only" instructions with the actual
  auto-discovery story (`assets/package.json` manifest + per-host
  `controllers.json` snippet for AssetMapper) and dropped the
  "scheduled v0.2" reference.

## [0.1.2] — 2026-05-12

**Install fix — please upgrade from v0.1.1.** Under the documented
happy-path install (`composer require polysource/easyadmin-filter-bridge`
alone, without `polysource/symfony-bundle`), every EasyAdmin index
page where any filter was applied crashed at render time with
`Twig\Error\SyntaxError: Unknown "saved_views_dropdown" function in
@PolysourceEasyAdminFilterBridge/crud/index.html.twig`. The bug
affected ALL EA indexes in the host app, not only controllers
using bridge features — the bridge prepends its `crud/index.html.twig`
globally into the `@EasyAdmin` namespace.

This release fixes the install blocker, restores the documented
`Polysource::filter()` flagship fluent chain, hardens the
`feature-branch → CI` install path (broken in v0.1.1 by the
path-repo + branch-alias coupling), and adds a bridge-alone smoke
test that would have caught this class of bug before v0.1.1 tag.

Hosts that pinned `polysource/easyadmin-filter-bridge: ^0.1.1`
should upgrade. Hosts on `^0.1` automatically pick this up.

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
