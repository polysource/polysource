<?php

declare(strict_types=1);

namespace Polysource\Widgets\Widget;

/**
 * Single-metric KPI tile — "Failed messages today: 47", "MRR:
 * $42,300", "P95 latency: 124ms".
 *
 * Optional fields:
 *  - `unit` rendered as a small label after the value
 *    ("ms", "$", "MAU")
 *  - `trend` ∈ {`up`, `down`, `flat`} — semantic, the template
 *    picks an arrow icon
 *  - `palette` = Bootstrap contextual class slug (without the
 *    `text-bg-` prefix). Defaults to `secondary`.
 */
final class CounterWidget extends AbstractWidget
{
    public function __construct(
        string $id,
        string $title,
        private readonly int|float $value,
        private readonly ?string $unit = null,
        private readonly ?string $trend = null,
        private readonly ?string $palette = null,
        int $columnSpan = 3,
    ) {
        parent::__construct($id, $title, $columnSpan);
    }

    public function getTemplate(): string
    {
        return '@PolysourceWidgets/widgets/counter.html.twig';
    }

    public function getViewData(): array
    {
        return [
            'value' => $this->value,
            'unit' => $this->unit,
            'trend' => $this->trend,
            'palette' => $this->palette ?? 'secondary',
        ];
    }
}
