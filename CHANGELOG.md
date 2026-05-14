# Changelog

All notable changes to Polysource are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic
Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added — `polysource/easyadmin-filter-bridge`

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
