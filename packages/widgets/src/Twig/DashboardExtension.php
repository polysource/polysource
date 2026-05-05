<?php

declare(strict_types=1);

namespace Polysource\Widgets\Twig;

use Polysource\Widgets\Dashboard\Dashboard;
use Polysource\Widgets\Dashboard\DashboardRegistry;
use Polysource\Widgets\Widget\WidgetInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing:
 *  - `render_widget(WidgetInterface)` — looks up the widget's
 *    template, merges `{widget, ...viewData}` into the render
 *    context.
 *  - `render_dashboard(Dashboard|string)` — renders the full
 *    `dashboard.html.twig` layout. Accepts either a Dashboard
 *    instance or a registered name (resolved via
 *    {@see DashboardRegistry}).
 *  - `polysource_dashboards()` — lists all registered dashboards
 *    (for nav menu rendering).
 */
final class DashboardExtension extends AbstractExtension
{
    public function __construct(
        private readonly Environment $twig,
        private readonly DashboardRegistry $registry,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_widget', $this->renderWidget(...), ['is_safe' => ['html']]),
            new TwigFunction('render_dashboard', $this->renderDashboard(...), ['is_safe' => ['html']]),
            new TwigFunction('polysource_dashboards', $this->dashboards(...)),
        ];
    }

    public function renderWidget(WidgetInterface $widget): string
    {
        return $this->twig->render(
            $widget->getTemplate(),
            array_merge($widget->getViewData(), ['widget' => $widget]),
        );
    }

    public function renderDashboard(Dashboard|string $dashboardOrName): string
    {
        $dashboard = $dashboardOrName instanceof Dashboard
            ? $dashboardOrName
            : $this->registry->get($dashboardOrName);

        if (null === $dashboard) {
            return '';
        }

        return $this->twig->render(
            '@PolysourceWidgets/dashboard.html.twig',
            ['dashboard' => $dashboard],
        );
    }

    /**
     * @return list<Dashboard>
     */
    public function dashboards(): array
    {
        return $this->registry->all();
    }
}
