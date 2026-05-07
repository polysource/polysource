# polysource/widgets

> Dashboard widgets for Polysource — KPI counters, top-N lists, sparkline charts.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## What it ships

- **`WidgetInterface`** (5-method contract) + **`AbstractWidget`** base.
- 3 concrete widgets:
  - **`CounterWidget`** — KPI counter ("12 failed messages", "$45 678 revenue today")
  - **`ListWidget`** — top-N list ("5 most recent orders")
  - **`ChartWidget`** — sparkline chart (textual fallback in v0.1)
- **`Dashboard`** immutable VO + **`DashboardRegistry`** (tagged_iterator `polysource.widgets.dashboard`).
- **`DashboardExtension`** Twig extension (`render_widget()`, `render_dashboard(Dashboard|string)`, `polysource_dashboards()`).
- 4 Bootstrap 5 templates (dashboard layout + counter/list/chart partials).

See [ADR-022](../../docs/adr/0022-dashboard-widgets.md). Drag-drop composition deferred to v0.2.

## Install

```bash
composer require polysource/widgets
```

Register the bundle:

```php
return [
    Polysource\Widgets\PolysourceWidgetsBundle::class => ['all' => true],
];
```

## Documentation

- [Widgets walkthrough](../../docs/user/widgets/)
