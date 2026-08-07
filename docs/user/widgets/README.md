# `polysource/widgets` — composable dashboard widgets

`polysource/widgets` is a Symfony bundle that ships the building
blocks for admin dashboards: KPI counters, top-N lists, sparkline
charts, all composed into named `Dashboard` value objects rendered
by a single Twig helper.

Per [ADR-022](../../adr/0022-dashboard-widgets.md), this is a
**separate package** opt-in. Apps that don't need a dashboard pay
no cost.

## What's in this folder

| File | What's in it |
|---|---|
| [installation.md](./installation.md) | Composer require, bundle registration, register a controller route to `render_dashboard()`. |
| [walkthrough.md](./walkthrough.md) | End-to-end: build 3 widgets, compose into a Dashboard, wire the route, render in Twig. |

## Status

**Shipped — v1.1.0 (2026-08-07).** Public API frozen since v1.0.0 under
strict SemVer, per ADR-022:
- `WidgetInterface` + `AbstractWidget`
- `CounterWidget`, `ListWidget`, `ChartWidget`
- `Dashboard` VO + `DashboardRegistry`
- Twig: `render_widget()`, `render_dashboard()`, `polysource_dashboards()`

## Why this matters

Existing Symfony admins (EasyAdmin, Sonata, API Platform) ask hosts
to hand-roll dashboard tiles in HTML/Twig with no shared contract.
`polysource/widgets` provides:
- A locked widget contract (5 methods) reusable across dashboards.
- A registry-based composition (one widget, many dashboards).
- Built-in Counter/List/Chart widgets covering 80% of admin needs.
- Bootstrap 5 grid layout out of the box.

## See also

- [ADR-022 — Dashboard widgets](../../adr/0022-dashboard-widgets.md)
- [ADR-018 — AdminPluginInterface + public contracts](../../adr/0018-admin-plugin-interface-and-public-contracts.md)
