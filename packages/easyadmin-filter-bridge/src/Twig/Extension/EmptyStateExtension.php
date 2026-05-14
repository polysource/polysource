<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing helpers for the empty-state design system
 * (v0.4.0 Task #20). When a filtered listing yields zero results,
 * hosts can render a contextual message + actionable CTAs:
 *
 *   - "No orders match status=paid + country=FR. [Clear filters]"
 *   - "No data yet."  (when no filters were applied)
 *
 * The helper exposes:
 *
 *   - `polysource_has_active_filters(): bool` — true if the current
 *     URL carries a `?filters[...]` slice. Use to gate the empty
 *     state message: "no results" vs. "no data yet".
 *
 *   - `polysource_clear_filters_url(): string` — the URL of the
 *     current page with every `filters[...]` (and the legacy
 *     `filter[...]` key) stripped. Use as the CTA href.
 *
 *   - `polysource_active_filters_summary(): list<array{property, value}>` —
 *     a flat description of the currently-applied filter slice for
 *     templates that want to enumerate them in the empty-state
 *     message.
 *
 * Hosts compose the message + CTA themselves — Polysource doesn't
 * ship an opinionated layout. The standard EA index template
 * already renders a "no results" row; hosts override it to call
 * these helpers.
 *
 * @since 0.4.0
 */
final class EmptyStateExtension extends AbstractExtension
{
    public function __construct(private readonly ?RequestStack $requestStack = null)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_has_active_filters', $this->hasActiveFilters(...)),
            new TwigFunction('polysource_clear_filters_url', $this->clearFiltersUrl(...)),
            new TwigFunction('polysource_active_filters_summary', $this->activeFiltersSummary(...)),
        ];
    }

    public function hasActiveFilters(): bool
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        return $request->query->has('filters') || $request->query->has('filter');
    }

    public function clearFiltersUrl(): string
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return '';
        }

        $query = $request->query->all();
        unset($query['filters'], $query['filter']);

        $qs = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        return '' !== $qs ? $request->getPathInfo() . '?' . $qs : $request->getPathInfo();
    }

    /**
     * @return list<array{property: string, value: string}>
     */
    public function activeFiltersSummary(): array
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $out = [];
        // EA uses `filters` (plural), older Polysource code used
        // `filter` — read both for robustness.
        foreach (['filters', 'filter'] as $key) {
            $raw = $request->query->all($key);
            foreach ($raw as $property => $value) {
                if (!\is_string($property) || '' === $property) {
                    continue;
                }
                $out[] = [
                    'property' => $property,
                    'value' => $this->summariseValue($value),
                ];
            }
        }

        return $out;
    }

    private function summariseValue(mixed $value): string
    {
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if (\is_array($value)) {
            // EA expanded shape: `{value: "...", comparison: "!="}`.
            // Or a list of values for multi-select. Both are
            // summarisable as a comma-joined string.
            if (isset($value['value']) && \is_scalar($value['value'])) {
                return (string) $value['value'];
            }
            $scalars = [];
            foreach ($value as $v) {
                if (\is_scalar($v)) {
                    $scalars[] = (string) $v;
                }
            }

            return implode(', ', $scalars);
        }

        return '';
    }
}
