<?php

declare(strict_types=1);

namespace Polysource\Bundle\Twig;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers consumed by the index page to render the active-filter
 * chips bar. Kept small on purpose:
 *
 * - `polysource_active_filters(context)`        → list<array{name, label, value, removeUrl}>
 * - `polysource_clear_filters_url(context)`     → string
 * - `polysource_apply_filter_url(context, ..)`  → string (form submit target)
 * - `polysource_saved_views_supported()`        → bool — kept for
 *   backward compat with v0.1.x templates; always returns true since
 *   polysource/filter is a hard require of this bundle (v0.1.4+).
 *
 * No HTML is produced here — the rendering lives in
 * `@Polysource/_filter_chips.html.twig` and `@Polysource/_filters_form.html.twig`
 * so hosts can override.
 *
 * Note: the `saved_views_dropdown()` Twig function is owned by
 * `polysource/filter::SavedViewExtension` (since v0.1.4). Previously
 * this bundle owned it with a delegation fallback when filter was
 * absent, but that design forced bridge-alone installs through a
 * stub-and-gate workaround. Filter is now a hard dep of this bundle
 * and a transitive dep of `polysource/easyadmin-filter-bridge`, so
 * the real function is always reachable from either install path.
 */
final class PolysourceFilterExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_active_filters', $this->activeFilters(...)),
            new TwigFunction('polysource_clear_filters_url', $this->clearFiltersUrl(...)),
            new TwigFunction('polysource_apply_filter_url', $this->applyFilterUrl(...)),
            new TwigFunction('polysource_remove_filter_url', $this->removeFilterUrl(...)),
            new TwigFunction('polysource_saved_views_supported', $this->savedViewsSupported(...)),
        ];
    }

    /**
     * @return list<array{name: string, label: string, operator: string, displayValue: string, removeUrl: string}>
     */
    public function activeFilters(AdminContext $context): array
    {
        $filterDefs = [];
        foreach ($context->resource->configureFilters() as $filter) {
            $filterDefs[$filter->getProperty()] = $filter;
        }

        $chips = [];
        foreach ($context->query->filters as $name => $criterion) {
            \assert($criterion instanceof FilterCriterion);
            $def = $filterDefs[$name] ?? null;
            $label = null !== $def ? $def->getLabel() : $name;
            $chips[] = [
                'name' => (string) $name,
                'label' => $label,
                'operator' => $criterion->operator->value,
                'displayValue' => self::formatValue($criterion->value),
                'removeUrl' => $this->removeFilterUrl($context, (string) $name),
            ];
        }

        return $chips;
    }

    public function clearFiltersUrl(AdminContext $context): string
    {
        return $this->buildIndexUrl($context, []);
    }

    public function applyFilterUrl(AdminContext $context, string $name, mixed $value, string $operator = 'eq'): string
    {
        $filters = self::queryFiltersAsArray($context->query);
        // Twig surface stays string-based for template ergonomics — the
        // operator gets validated at AdminContextResolver entry point via
        // FilterOperator::tryFrom().
        $filters[$name] = self::criterionForUrl($name, $operator, $value);

        return $this->buildIndexUrl($context, $filters);
    }

    public function removeFilterUrl(AdminContext $context, string $name): string
    {
        $filters = self::queryFiltersAsArray($context->query);
        unset($filters[$name]);

        return $this->buildIndexUrl($context, $filters);
    }

    public function savedViewsSupported(): bool
    {
        // Since v0.1.4 polysource/filter is a hard require of this
        // bundle (saved-views is a core admin engine feature, not an
        // optional plugin). The class is therefore always loadable
        // when this extension is itself loaded. Kept as a function
        // so v0.1.x templates that gate on it still parse — the
        // gate is now trivially true.
        return true;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function buildIndexUrl(AdminContext $context, array $filters): string
    {
        $resourceName = $context->resource->getName();
        $route = 'polysource_' . str_replace('-', '_', $resourceName) . '_index';
        $params = ['resourceName' => $resourceName];
        if ($filters !== []) {
            $params['filter'] = $filters;
        }

        return $this->router->generate($route, $params);
    }

    /**
     * @return array<string, mixed>
     */
    private static function queryFiltersAsArray(DataQuery $query): array
    {
        $out = [];
        foreach ($query->filters as $name => $criterion) {
            \assert($criterion instanceof FilterCriterion);
            $out[$name] = self::criterionForUrl($name, $criterion->operator->value, $criterion->value);
        }

        return $out;
    }

    /**
     * Encode a criterion into the URL shape understood by
     * AdminContextResolver. `eq` with a scalar collapses to the legacy
     * shape `?filter[name]=value` for backwards compatibility; richer
     * operators or list values use the nested shape
     * `?filter[name][op]=...&filter[name][value(s)]=...`.
     */
    private static function criterionForUrl(string $name, string $operator, mixed $value): mixed
    {
        if ('eq' === $operator && \is_scalar($value)) {
            return (string) $value;
        }

        $payload = ['op' => $operator];
        if (\is_array($value)) {
            $payload['values'] = array_map(static fn ($v): string => \is_scalar($v) ? (string) $v : '', $value);
        } elseif (\is_scalar($value)) {
            $payload['value'] = (string) $value;
        }

        return $payload;
    }

    private static function formatValue(mixed $value): string
    {
        if (\is_array($value)) {
            return implode(', ', array_map(static fn ($v): string => \is_scalar($v) ? (string) $v : '', $value));
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
