# Roadmap

> What's shipped, what's next. Versions follow [SemVer](https://semver.org)
> from v1.0; pre-1.0 minors may break the surface where the changelog
> says so.

## v0.1 — shipped

**16 packages**, full multi-version CI matrix
(PHP 8.1→8.4 × Symfony 6.4 / 7.2 / 7.4 × EasyAdmin 4.24+ / 5.0+),
29 browser E2E + 15 adapter real-container tests + 782
unit/functional tests.

Capabilities included:

- **Two products, same primitives**:
  - `polysource/easyadmin-filter-bridge` — drop-in for an existing
    EasyAdmin app
  - `polysource/symfony-bundle` (+ `polysource/admin` family) —
    standalone admin engine for non-Doctrine resources
- **6 adapters**: Messenger, Doctrine read-only, Redis (hashes),
  Flysystem (S3 / local / MinIO / Azure / GCS), HTTP REST, Meilisearch
- **5 cross-cutting capabilities**: audit log, dashboard widgets,
  Cmd+K global search, Symfony Workflow bridge, async bulk actions
- **Filter primitives** + saved views + tabbed filter modal + chips
  bar — usable standalone and via the EA bridge

See [`CHANGELOG.md`](./CHANGELOG.md) for the full feature list.

## v0.2 — next

Themes the next minor focuses on:

- Built-in concrete field types (`TextField`, `BooleanField`,
  `DateTimeField`, `IdField`, `CodeField`, `PercentageField`) so
  hosts stop pre-formatting at the data-source layer
- Polish-shell convergence: align Polysource standalone listings
  with EasyAdmin's visual language (sort arrows, button-styled
  actions, status badges, top search bar) — tracked as a
  help-wanted issue, contributions welcome
- Cookbook entries for real-world adapter integrations
  (CRM REST APIs, S3 lifecycle policies, Meilisearch reindex jobs)
- Wider i18n coverage (currently EN + FR — community-driven from
  v0.2)

## v0.3+

Direction (not committed):

- Builder-style DX (Filament-inspired) for resource declaration
- Inline editing on index pages where the data source supports it
- Conditional fields + step-by-step wizards
- Drag-drop dashboard composition (today: code-defined widgets)
- Bridges for SonataAdmin filters

## v1.0 — API freeze

The public surface (`Polysource\Plugin\*` + the documented
`*Interface` types in each package) freezes on v1.0 per
[ADR-018](./docs/adr/0018-admin-plugin-interface-and-public-contracts.md).
SemVer applies strictly from that point on; breaking changes ship
in major versions only.

## Out of scope

The boundary stays where it always was (cf.
[ADR-012](./docs/adr/0012-dual-product-positioning.md) and
[ADR-017](./docs/adr/0017-cherry-picking-from-filament-study.md)):

- Replacing EasyAdmin on Doctrine ORM
- BI dashboards (Grafana / Metabase territory)
- No-code internal-tool builders (Retool / Appsmith territory)
- Multi-tenant SaaS hosting
- Authentication providers (use the host app's firewall)

## Want to help?

The 6 first "help wanted" issues are labeled at launch covering
adapter ports, the Sonata bridge, GH-Action automation, locale
translations, cookbook entries, and the visual polish-shell
project. Comment on the one you want to claim before opening a PR.
