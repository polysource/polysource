<?php

declare(strict_types=1);

namespace Polysource\Widgets\Dashboard;

use InvalidArgumentException;

/**
 * Name → Dashboard map.
 *
 * Hosts register Dashboard instances as services tagged
 * `polysource.widgets.dashboard` and the registry is constructed
 * with a `tagged_iterator(...)` of those services.
 *
 * Duplicate names trigger a hard error at construction time —
 * dashboards are user-facing routes; a silent override would be
 * surprising on prod.
 */
final class DashboardRegistry
{
    /** @var array<string, Dashboard> */
    private array $byName = [];

    /**
     * @param iterable<Dashboard> $dashboards services tagged `polysource.widgets.dashboard`
     */
    public function __construct(iterable $dashboards)
    {
        foreach ($dashboards as $dashboard) {
            if (isset($this->byName[$dashboard->name])) {
                throw new InvalidArgumentException(\sprintf('Two dashboards declared name "%s" — registry refuses silent overrides.', $dashboard->name));
            }
            $this->byName[$dashboard->name] = $dashboard;
        }
    }

    public function get(string $name): ?Dashboard
    {
        return $this->byName[$name] ?? null;
    }

    /**
     * @return list<Dashboard>
     */
    public function all(): array
    {
        return array_values($this->byName);
    }

    public function has(string $name): bool
    {
        return isset($this->byName[$name]);
    }
}
