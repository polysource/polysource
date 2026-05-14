<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use BackedEnum;
use Stringable;
use Symfony\Component\HttpFoundation\RequestStack;
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
    public function __construct(private readonly ?RequestStack $requestStack = null)
    {
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

    public function render(
        string $property,
        mixed $value,
        ?string $label = null,
    ): Markup {
        $stringValue = $this->stringify($value);
        if ('' === $stringValue) {
            // Skip menu rendering for empty values — applying
            // `?filter[X]=` wouldn't filter anything meaningful.
            return new Markup('', 'UTF-8');
        }

        $label ??= ucfirst(str_replace('_', ' ', $property));
        $labelEscaped = htmlspecialchars($label, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $valueDisplay = mb_strimwidth($stringValue, 0, 32, '…', 'UTF-8');
        $valueEscaped = htmlspecialchars($valueDisplay, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $urlEq = htmlspecialchars($this->urlFor($property, $value, 'eq'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $urlNeq = htmlspecialchars($this->urlFor($property, $value, 'neq'), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $urlOnly = htmlspecialchars($this->urlFor($property, $value, 'eq', replace: true), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $html = <<<HTML
            <span class="polysource-cell-filter-menu dropdown">
                <button type="button"
                        class="btn btn-sm btn-link polysource-cell-filter-menu__trigger"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="Filter options for {$labelEscaped}">
                    <span aria-hidden="true">⋮</span>
                </button>
                <ul class="dropdown-menu polysource-cell-filter-menu__list">
                    <li><a class="dropdown-item" href="{$urlEq}">Filter where {$labelEscaped} = "{$valueEscaped}"</a></li>
                    <li><a class="dropdown-item" href="{$urlNeq}">Exclude {$labelEscaped} = "{$valueEscaped}"</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{$urlOnly}">Show only this {$labelEscaped}</a></li>
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

        $query = null !== $request && !$replace
            ? $request->query->all()
            : [];
        // EA conventional shape: `filters[<property>][value]=<value>&filters[<property>][comparison]=<op>`.
        // The bridge accepts both `filter` and `filters` URL keys, but
        // EA's own URL build uses `filters` (plural). Match that.
        $existing = isset($query['filters']) && \is_array($query['filters']) ? $query['filters'] : [];
        $existing[$property] = 'eq' === $operator
            ? $stringValue
            : ['comparison' => '!=', 'value' => $stringValue];
        $query['filters'] = $existing;

        $qs = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        return '' !== $qs ? $path . '?' . $qs : $path;
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
