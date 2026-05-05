<?php

declare(strict_types=1);

namespace Polysource\Widgets\Widget;

/**
 * Top-N records widget — "Last 5 failed messages", "Top 10
 * customers by MRR", "Recent compliance audits".
 *
 * The widget keeps a tiny callback contract for label + href so
 * hosts can pass any iterable of domain objects without first
 * mapping them to a UI shape.
 *
 * The view-data array carries a list of `{label, href}` rows that
 * the Twig template iterates over.
 */
final class ListWidget extends AbstractWidget
{
    /**
     * @param iterable<mixed>                 $items
     * @param callable(mixed): string         $labelFn
     * @param (callable(mixed): ?string)|null $hrefFn
     */
    public function __construct(
        string $id,
        string $title,
        private readonly iterable $items,
        private readonly mixed $labelFn,
        private readonly mixed $hrefFn = null,
        int $columnSpan = 4,
    ) {
        parent::__construct($id, $title, $columnSpan);
    }

    public function getTemplate(): string
    {
        return '@PolysourceWidgets/widgets/list.html.twig';
    }

    public function getViewData(): array
    {
        $rows = [];
        $labelFn = $this->labelFn;
        $hrefFn = $this->hrefFn;
        foreach ($this->items as $item) {
            $rows[] = [
                'label' => (string) $labelFn($item),
                'href' => null !== $hrefFn ? $hrefFn($item) : null,
            ];
        }

        return ['rows' => $rows];
    }
}
