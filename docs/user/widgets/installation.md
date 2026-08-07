# Installing `polysource/widgets`

## 1. Composer

```bash
composer require polysource/widgets
```

Pure-PHP package with Twig + Symfony DI deps. No Doctrine, no
async, no JS framework requirements.

## 2. Register the bundle

`config/bundles.php`:

```php
return [
    // …existing bundles…
    Polysource\Widgets\PolysourceWidgetsBundle::class => ['all' => true],
];
```

Discoverable via `bin/console polysource:plugins:list`.

## 3. Wire a controller route

The bundle ships **no** controller — hosts decide where dashboards
live. The simplest pattern:

```php
// src/Controller/AdminController.php
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly DashboardRegistry $registry,
    ) {
    }

    #[Route('/admin/dashboard/{name}', name: 'admin_dashboard')]
    public function dashboard(string $name = 'overview'): Response
    {
        $dashboard = $this->registry->get($name);
        if (null === $dashboard) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/dashboard.html.twig', [
            'dashboard' => $dashboard,
        ]);
    }
}
```

```twig
{# templates/admin/dashboard.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
    {{ render_dashboard(dashboard) }}
{% endblock %}
```

Or invoke `render_dashboard('overview')` (string form) directly
from any template — the helper resolves through the registry.

## 4. Define your first dashboard

Hosts ship one or more `Dashboard` services tagged
`polysource.widgets.dashboard`:

```yaml
# config/services.yaml
services:
    App\Dashboard\OverviewDashboard:
        tags: ['polysource.widgets.dashboard']
```

```php
// src/Dashboard/OverviewDashboard.php
use Polysource\Widgets\Dashboard\Dashboard;
use Polysource\Widgets\Widget\CounterWidget;
use Polysource\Widgets\Widget\ListWidget;

final class OverviewDashboard extends Dashboard
{
    public function __construct(FailedMessageRepository $repo)
    {
        parent::__construct(
            name: 'overview',
            title: 'Overview',
            rows: [
                [
                    new CounterWidget('failed-today', 'Failed today', $repo->countToday(), palette: 'danger'),
                    new CounterWidget('failed-week', 'Failed (7d)', $repo->countLast7Days(), palette: 'warning'),
                    new CounterWidget('uptime', 'Uptime', 99.93, '%', palette: 'success'),
                ],
                [
                    new ListWidget(
                        id: 'recent-failures',
                        title: 'Recent failures',
                        items: $repo->latest(5),
                        labelFn: static fn (FailedMessage $m): string => $m->subject,
                        hrefFn: static fn (FailedMessage $m): string => '/admin/failed-messages/' . $m->id,
                    ),
                ],
            ],
        );
    }
}
```

The Dashboard is constructed with its widgets injected (or built
from injected services) — this keeps composition declarative.

## 5. Smoke-test

```bash
bin/console polysource:plugins:list
# Expect: polysource/widgets   1.1.0
```

Visit `/admin/dashboard/overview`.

## See also

- [walkthrough.md](./walkthrough.md) — end-to-end demo.
- [ADR-022](../../adr/0022-dashboard-widgets.md) — design rationale.
