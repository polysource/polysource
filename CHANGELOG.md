# Changelog

All notable changes to Polysource are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic
Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Decided

- ADR-017 — Cherry-picking from the exploratory "Filament-for-Symfony"
  study. Locks Phase 11+ to 7 features (plugin architecture, saved
  views, dashboard widgets, bulk async, Symfony Workflow integration,
  global search + command palette, audit non-Doctrine actions). Rejects
  Doctrine-shaped features that would put Polysource in frontal
  competition with EasyAdmin (audit on entities, multi-tenancy filter,
  inline editing, conditional fields, wizards, polymorphic, CSV/Excel
  for Doctrine, real-time collab, visual builders, headless API,
  2FA/SSO/PAT wraps).
- ADR-018 — Foundation plugin architecture for Phase 10+:
  `AdminPluginInterface` + `#[AsPlugin]` attribute + `PluginRegistry` +
  per-capability ADRs (ADR-020 → ADR-025). Tag-based extension stays
  primary; the interface adds a metadata + introspection layer.
  Semver strict on `Polysource\Plugin\*` from v1.0.0.

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
