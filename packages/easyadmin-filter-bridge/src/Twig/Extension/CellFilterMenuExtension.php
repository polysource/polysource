<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use BackedEnum;
use Polysource\Filter\Url\FilterUrlBuilder;
use Polysource\Filter\Url\OperatorMap;
use Stringable;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;
use UnitEnum;

/**
 * Twig extension exposing `polysource_cell_filter_menu(property, value, label)`
 * — renders a small dropdown menu with three filter-from-cell actions:
 *
 *   - "Filter where {label} = this" → adds `?filter[X]=value` to URL
 *   - "Exclude {label} = this"      → adds `?filter[X][op]=neq` slice
 *   - "Show only this {label}"      → REPLACES URL filters to just this slice
 *
 * Each item is a plain `<a href="...">` so the feature works without
 * any JS — clicking navigates to a filtered URL, the listing
 * re-renders with the slice applied. Per ADR-027 progressive
 * enhancement.
 *
 * Usage in host EA index template (typically via a Field formatter):
 *
 *     {% block table_body_cell %}
 *         {{ parent() }}
 *         {{ polysource_cell_filter_menu(field.property, field.value, field.label) }}
 *     {% endblock %}
 *
 * Or anywhere in a custom column renderer. The helper returns Twig\Markup
 * so the host can interpolate the HTML directly without `|raw`.
 *
 * @since 0.4.0
 */
final class CellFilterMenuExtension extends AbstractExtension
{
    use TranslatorFallbackTrait;

    public function __construct(
        private readonly ?RequestStack $requestStack = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'polysource_cell_filter_menu',
                $this->render(...),
                ['is_safe' => ['html']],
            ),
            new TwigFunction(
                'polysource_cell_filter_url',
                $this->urlFor(...),
            ),
        ];
    }

    /**
     * @param mixed $filterValue optional — value to put in the URL
     *                           filter slice. Defaults to `$value` (the
     *                           display value). Hosts pass an entity ID
     *                           here for `EntityFilter` columns where
     *                           the display string (`__toString()`)
     *                           differs from the primary key.
     */
    public function render(
        string $property,
        mixed $value,
        ?string $label = null,
        mixed $filterValue = null,
    ): Markup {
        $stringValue = $this->stringify($value);
        if ('' === $stringValue) {
            // Skip menu rendering for empty values — applying
            // `?filter[X]=` wouldn't filter anything meaningful.
            return new Markup('', 'UTF-8');
        }

        // When the caller didn't provide an explicit filter value,
        // fall back to the display value — preserves prior behaviour
        // for scalar columns. For entity fields, hosts MUST pass the
        // entity's id as `$filterValue` because the display string
        // (e.g. `User #42 — Jane Doe`) cannot be matched by EA's
        // EntityFilter which expects the primary key.
        $resolvedFilterValue = null !== $filterValue ? $filterValue : $value;

        $label ??= ucfirst(str_replace('_', ' ', $property));
        $valueDisplay = mb_strimwidth($stringValue, 0, 32, '…', 'UTF-8');

        // Translate first with RAW params, escape the final strings
        // once — avoids double-escaping the label/value while still
        // covering unsafe characters coming from the catalog itself.
        $params = ['%label%' => $label, '%value%' => $valueDisplay];
        $esc = static fn (string $v): string => htmlspecialchars($v, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $textOptions = $esc($this->transWithFallback('polysource.cell_menu.options', 'Filter options for %label%', $params));
        $textWhere = $esc($this->transWithFallback('polysource.cell_menu.filter_where', 'Filter where %label% = "%value%"', $params));
        $textExclude = $esc($this->transWithFallback('polysource.cell_menu.exclude', 'Exclude %label% = "%value%"', $params));
        $textOnly = $esc($this->transWithFallback('polysource.cell_menu.show_only', 'Show only this %label%', $params));

        $urlEq = htmlspecialchars($this->urlFor($property, $resolvedFilterValue, 'eq'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $urlNeq = htmlspecialchars($this->urlFor($property, $resolvedFilterValue, 'neq'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $urlOnly = htmlspecialchars($this->urlFor($property, $resolvedFilterValue, 'eq', replace: true), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $html = <<<HTML
            <span class="polysource-cell-filter-menu dropdown">
                <button type="button"
                        class="btn btn-sm btn-link polysource-cell-filter-menu__trigger"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="{$textOptions}">
                    <span aria-hidden="true">⋮</span>
                </button>
                <ul class="dropdown-menu polysource-cell-filter-menu__list">
                    <li><a class="dropdown-item" href="{$urlEq}">{$textWhere}</a></li>
                    <li><a class="dropdown-item" href="{$urlNeq}">{$textExclude}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{$urlOnly}">{$textOnly}</a></li>
                </ul>
            </span>
            HTML;

        return new Markup($html, 'UTF-8');
    }

    /**
     * Build the filter URL for the given action.
     *
     * @param 'eq'|'neq' $operator the filter operator
     * @param bool       $replace  if true, drop existing filters and use only this slice
     */
    public function urlFor(
        string $property,
        mixed $value,
        string $operator = 'eq',
        bool $replace = false,
    ): string {
        $stringValue = $this->stringify($value);

        $request = $this->requestStack?->getCurrentRequest();
        $path = null !== $request ? $request->getPathInfo() : '';
        $existingQuery = null !== $request ? $request->query->all() : [];

        // Delegate the URL assembly to FilterUrlBuilder — the single
        // source of truth for the `filters[...]` query shape. The
        // builder always emits the expanded EA shape
        // (`filters[<prop>][comparison]=<op>&[value]=<v>`) regardless
        // of operator; the scalar shorthand `filters[<prop>]=<v>` is
        // silently dropped by EA's filter pipeline. Dogfood signal #10,
        // 2026-05-17 — shipped in v0.8.1, extracted to a shared class
        // in v0.9.0 to prevent the regression from recurring elsewhere.
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: $existingQuery,
            property: $property,
            eaOperator: OperatorMap::toEa($operator),
            value: $stringValue,
            replace: $replace,
        );

        return FilterUrlBuilder::toPathWithQuery($path, $merged);
    }

    private function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }
}
