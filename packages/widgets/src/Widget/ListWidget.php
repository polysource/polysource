<?php

declare(strict_types=1);

namespace Polysource\Widgets\Widget;

use Closure;

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
 *
 * ## Per-request data (user-scoped widgets)
 *
 * `$items` can be a `callable(): iterable` instead of an `iterable`.
 * When provided as a callable, it is invoked at `getViewData()` time
 * — i.e. once per HTTP request that renders the dashboard. This is
 * the supported way to plug user-scoped data (e.g. "recently viewed
 * orders for the current operator") into a widget that lives in
 * a Dashboard registered at boot time:
 *
 * ```php
 * new ListWidget(
 *     id: 'recent-orders',
 *     title: 'Recently viewed',
 *     items: fn (): iterable => $recentRecordsService->recentForCurrentUser('orders', limit: 5),
 *     labelFn: fn (Order $o): string => $o->getReference(),
 *     hrefFn: fn (Order $o): string => $urlGenerator->generate('order_show', ['id' => $o->id]),
 * );
 * ```
 *
 * Pre-v0.6.1 widgets had to pre-bind `$items` at construction time,
 * which the `DashboardRegistry`'s boot-time caching froze for the
 * lifetime of the container — user-scoped data was impossible
 * without bypassing the registry entirely. Surfaced 2026-05-14 by
 * the showcase recently-viewed-orders integration.
 */
final class ListWidget extends AbstractWidget
{
    /**
     * @param iterable<mixed>|Closure(): iterable<mixed> $items
     * @param callable(mixed): string                     $labelFn
     * @param (callable(mixed): ?string)|null             $hrefFn
     */
    public function __construct(
        string $id,
        string $title,
        private readonly iterable|Closure $items,
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

        // Resolve the per-request callable on demand; eager iterables
        // pass through unchanged.
        if ($this->items instanceof Closure) {
            $resolved = ($this->items)();
            $items = is_iterable($resolved) ? $resolved : [];
        } else {
            $items = $this->items;
        }

        foreach ($items as $item) {
            $rows[] = [
                'label' => (string) $labelFn($item),
                'href' => null !== $hrefFn ? $hrefFn($item) : null,
            ];
        }

        return ['rows' => $rows];
    }
}
