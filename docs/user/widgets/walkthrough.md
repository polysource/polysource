# Widgets walkthrough — first hour

After [installation](./installation.md), this page walks you from
zero to a 3-widget dashboard rendered at `/admin/dashboard/overview`.

## 1. Pick your widgets

Three built-in types cover 80% of admin needs:

| Type | When | Key fields |
|---|---|---|
| `CounterWidget` | KPIs ("MRR: $42.3k", "Failed messages: 47") | `value`, `unit?`, `trend?`, `palette?` |
| `ListWidget`    | Top-N records ("Recent failures", "Top customers") | `items`, `labelFn`, `hrefFn?` |
| `ChartWidget`   | Sparkline data points (textual fallback v0.1) | `points: [{label, value}]`, `type ∈ {line, bar}` |

Hosts shipping custom widgets implement `WidgetInterface` and ship
their own template.

## 2. Compose a Dashboard

Dashboards are immutable VOs constructed in code:

```php
$dashboard = new Dashboard(
    name: 'overview',
    title: 'Overview',
    rows: [
        // Row 1: 3 counter tiles (col-md-3 each)
        [
            new CounterWidget('mrr', 'MRR', 42300, '$', palette: 'success'),
            new CounterWidget('churn', 'Churn rate', 2.3, '%', trend: 'down', palette: 'success'),
            new CounterWidget('p95', 'P95 latency', 124, 'ms'),
        ],
        // Row 2: full-width list
        [
            new ListWidget(
                id: 'recent-signups',
                title: 'Recent signups',
                items: $signups,
                labelFn: static fn (User $u): string => $u->email,
                hrefFn: static fn (User $u): string => '/admin/users/' . $u->id,
                columnSpan: 12,
            ),
        ],
        // Row 3: 2 charts side by side (col-md-6 each)
        [
            new ChartWidget('mrr-trend', 'MRR (last 30 days)', $mrrPoints, 'line'),
            new ChartWidget('signups-trend', 'Signups (last 30 days)', $signupPoints, 'bar'),
        ],
    ],
);
```

Bootstrap rows just sum widget `columnSpan` values. Mix and match
freely — `[col-md-4, col-md-4, col-md-4]`, `[col-md-6, col-md-6]`,
`[col-md-12]`, etc.

## 3. Register and route

```yaml
# config/services.yaml
services:
    App\Dashboard\OverviewDashboard:
        tags: ['polysource.widgets.dashboard']
```

```php
#[Route('/admin/dashboard', name: 'admin_dashboard')]
public function index(DashboardRegistry $registry): Response
{
    return $this->render('admin/dashboard.html.twig', [
        'dashboard' => $registry->get('overview'),
    ]);
}
```

```twig
{# templates/admin/dashboard.html.twig #}
{% extends 'base.html.twig' %}
{% block body %}
    {{ render_dashboard(dashboard) }}
{% endblock %}
```

## 4. Multiple dashboards + nav

Tag every dashboard service. The `polysource_dashboards()` Twig
function lists them — perfect for a sidebar:

```twig
<nav class="polysource-dashboards-nav">
    <ul>
        {% for d in polysource_dashboards() %}
            <li>
                <a href="{{ path('admin_dashboard', {name: d.name}) }}">
                    {{ d.title }}
                </a>
            </li>
        {% endfor %}
    </ul>
</nav>
```

## 5. Audit + permission

Dashboard rendering doesn't go through `ActionController::safelyRun()`
so the audit log (ADR-020) doesn't trace dashboard loads by
default. If you need that, ship a `kernel.controller` listener
that emits an `ActionExecutedEvent` from your dashboard route — or
listen to your own custom event.

For permissions: gate the controller via standard Symfony
`#[IsGranted('ROLE_ADMIN')]`. Per-widget gating is host-side
(don't construct widgets the user isn't allowed to see).

## 6. Custom widget types

Implement `WidgetInterface` and ship your template:

```php
final class HealthcheckWidget implements WidgetInterface
{
    public function getId(): string         { return 'healthcheck'; }
    public function getTitle(): string      { return 'System health'; }
    public function getColumnSpan(): int    { return 6; }
    public function getTemplate(): string   { return '@App/widgets/healthcheck.html.twig'; }
    public function getViewData(): array
    {
        return ['probes' => $this->probes->all()];
    }
}
```

Compose into any Dashboard the same way.

## See also

- [installation.md](./installation.md) — wiring basics.
- [ADR-022](../../adr/0022-dashboard-widgets.md) — design rationale,
  what's deferred to v0.2 (Chart.js, drag-drop layouts).
