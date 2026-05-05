<?php

declare(strict_types=1);

namespace Polysource\Widgets\Dashboard;

use InvalidArgumentException;
use Polysource\Widgets\Widget\WidgetInterface;

/**
 * Declarative composition of widgets — a dashboard is just a name,
 * a title, and a list of *rows*. Each row is a horizontal list of
 * widget instances; the Twig layout sums their `getColumnSpan()`
 * into Bootstrap col-md-N classes.
 *
 * Immutable. Hosts that want runtime composition build a fresh
 * `Dashboard` per request rather than mutating one.
 */
final class Dashboard
{
    /**
     * @param list<list<WidgetInterface>> $rows
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly array $rows,
    ) {
        if ('' === $name) {
            throw new InvalidArgumentException('Dashboard name cannot be empty.');
        }
        if ('' === $title) {
            throw new InvalidArgumentException('Dashboard title cannot be empty.');
        }
        foreach ($rows as $i => $row) {
            if (!\is_array($row)) {
                throw new InvalidArgumentException(\sprintf('Dashboard row %d must be a list of WidgetInterface instances.', $i));
            }
            foreach ($row as $j => $widget) {
                if (!$widget instanceof WidgetInterface) {
                    throw new InvalidArgumentException(\sprintf('Dashboard row %d cell %d must implement WidgetInterface, got %s.', $i, $j, get_debug_type($widget)));
                }
            }
        }
    }
}
