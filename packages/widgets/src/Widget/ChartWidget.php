<?php

declare(strict_types=1);

namespace Polysource\Widgets\Widget;

use InvalidArgumentException;

/**
 * Sparkline / micro-chart widget.
 *
 * v0.1 ships a *textual* representation (small table of points) —
 * the rendering is intentionally minimal so hosts can override the
 * `widgets/chart.html.twig` template with a real chart engine
 * (Chart.js, Apache ECharts, Frappé Charts) without fighting our
 * defaults.
 *
 * v0.2 will ship a `ChartJsExtension` that drops in proper line /
 * bar charts; the data shape stays compatible.
 */
final class ChartWidget extends AbstractWidget
{
    public const TYPE_LINE = 'line';
    public const TYPE_BAR = 'bar';

    /**
     * @param list<array{label: string, value: int|float}> $points
     */
    public function __construct(
        string $id,
        string $title,
        private readonly array $points,
        private readonly string $type = self::TYPE_LINE,
        int $columnSpan = 6,
    ) {
        parent::__construct($id, $title, $columnSpan);

        if (!\in_array($type, [self::TYPE_LINE, self::TYPE_BAR], true)) {
            throw new InvalidArgumentException(\sprintf('ChartWidget type must be "line" or "bar", got "%s".', $type));
        }
    }

    public function getTemplate(): string
    {
        return '@PolysourceWidgets/widgets/chart.html.twig';
    }

    public function getViewData(): array
    {
        return [
            'type' => $this->type,
            'points' => $this->points,
        ];
    }
}
