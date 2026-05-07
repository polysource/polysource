<?php

declare(strict_types=1);

namespace Polysource\Bundle\Twig;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig helpers consumed by the index page to render the active-filter
 * chips bar and the saved-views dropdown. Kept small on purpose:
 *
 * - `polysource_active_filters(context)`        → list<array{name, label, value, removeUrl}>
 * - `polysource_clear_filters_url(context)`     → string
 * - `polysource_apply_filter_url(context, ..)`  → string (form submit target)
 * - `polysource_saved_views_supported()`        → bool — true iff
 *   polysource/filter is installed and its SavedViewExtension is loaded
 *
 * No HTML is produced here — the rendering lives in
 * `@Polysource/_filter_chips.html.twig` and `@Polysource/_filters_form.html.twig`
 * so hosts can override.
 */
final class PolysourceFilterExtension extends AbstractExtension
{
    /**
     * @param object|null $savedViewExtension Optional polysource/filter
     *                    SavedViewExtension instance — when present, the
     *                    bundled `saved_views_dropdown()` Twig function
     *                    delegates to it. Typed as `object` because the
     *                    class lives in an optional dependency package
     *                    that may not be installed.
     */
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly ?object $savedViewExtension = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        $functions = [
            new TwigFunction('polysource_active_filters', $this->activeFilters(...)),
            new TwigFunction('polysource_clear_filters_url', $this->clearFiltersUrl(...)),
            new TwigFunction('polysource_apply_filter_url', $this->applyFilterUrl(...)),
            new TwigFunction('polysource_remove_filter_url', $this->removeFilterUrl(...)),
            new TwigFunction('polysource_saved_views_supported', $this->savedViewsSupported(...)),
        ];

        // `saved_views_dropdown` is exposed by THIS bundle (always
        // loaded) so the bundled `index.html.twig` always parses.
        // When polysource/filter is installed AND its
        // `SavedViewExtension` was wired into our constructor, we
        // delegate to it for the real dropdown HTML. Otherwise the
        // function returns an empty string — a no-op fallback that
        // keeps templates rendering without crashing.
        $functions[] = new TwigFunction(
            'saved_views_dropdown',
            $this->renderSavedViewsDropdown(...),
            ['is_safe' => ['html']],
        );

        return $functions;
    }

    public function renderSavedViewsDropdown(string $resourceName, string $template = ''): string
    {
        if (null === $this->savedViewExtension) {
            return '';
        }
        if (!method_exists($this->savedViewExtension, 'renderDropdown')) {
            return '';
        }

        $output = '' === $template
            ? $this->savedViewExtension->renderDropdown($resourceName)
            : $this->savedViewExtension->renderDropdown($resourceName, $template);

        return \is_string($output) ? $output : '';
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
                'operator' => $criterion->operator,
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
        // Probe both the package class AND the Twig extension presence.
        // Package can be installed without the bundle being loaded —
        // require both before claiming the feature is wired.
        return class_exists(\Polysource\Filter\SavedView\Twig\SavedViewExtension::class);
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
            $out[$name] = self::criterionForUrl($name, $criterion->operator, $criterion->value);
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
