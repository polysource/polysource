<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_quick_filter_row(property, label)`
 * (v0.4.0 Task #17) — renders a small `<form method="GET">` wrapping
 * an `<input name="filters[X]">` that submits to the current page
 * when the user presses Enter. The form preserves every other query
 * param (other filters, sort, page size) via hidden inputs.
 *
 * Used inside `<th>` cells in a custom EA index table override. The
 * resulting layout:
 *
 *     <th>
 *         Status
 *         <form method="GET" action="…">
 *             <input type="hidden" name="filters[country]" value="FR">
 *             <input type="hidden" name="sort[createdAt]" value="desc">
 *             <input name="filters[status]" value="" placeholder="filter…">
 *         </form>
 *     </th>
 *
 * Pure server-side, no JS required. Enter submits → page reloads
 * with the filter slice applied. Per ADR-027.
 *
 * @since 0.4.0
 */
final class QuickFilterRowExtension extends AbstractExtension
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
                'polysource_quick_filter_row',
                $this->render(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    public function render(string $property, ?string $placeholder = null): Markup
    {
        $request = $this->requestStack?->getCurrentRequest();
        $action = null !== $request ? $request->getPathInfo() : '';
        $current = $this->currentValueFor($property);
        $placeholder ??= 'filter…';

        $propertyEsc = htmlspecialchars($property, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $actionEsc = htmlspecialchars($action, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $currentEsc = htmlspecialchars($current, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $placeholderEsc = htmlspecialchars($placeholder, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $hiddenInputs = $this->buildHiddenInputs($property);

        $html = <<<HTML
            <form method="GET" action="{$actionEsc}" class="polysource-quick-filter-row">
                {$hiddenInputs}
                <input type="text"
                       name="filters[{$propertyEsc}]"
                       value="{$currentEsc}"
                       placeholder="{$placeholderEsc}"
                       class="form-control form-control-sm polysource-quick-filter-row__input"
                       autocomplete="off">
            </form>
            HTML;

        return new Markup($html, 'UTF-8');
    }

    private function currentValueFor(string $property): string
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return '';
        }
        $filters = $request->query->all('filters');
        if (!isset($filters[$property])) {
            return '';
        }
        $value = $filters[$property];
        if (\is_array($value) && isset($value['value']) && \is_scalar($value['value'])) {
            return (string) $value['value'];
        }

        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * Build `<input type="hidden">` markup for every non-filter query
     * param + every filter slice OTHER than the one our input owns.
     * Without these the GET submit would drop the existing URL state.
     */
    private function buildHiddenInputs(string $ourProperty): string
    {
        $request = $this->requestStack?->getCurrentRequest();
        if (null === $request) {
            return '';
        }

        $out = '';
        foreach ($request->query->all() as $key => $value) {
            if ('filters' === $key) {
                foreach ((array) $value as $property => $val) {
                    if ($property === $ourProperty) {
                        continue;
                    }
                    $out .= $this->renderHiddenForFilter((string) $property, $val);
                }
                continue;
            }
            $out .= $this->renderHiddenForKey((string) $key, $value);
        }

        return $out;
    }

    private function renderHiddenForFilter(string $property, mixed $value): string
    {
        if (\is_scalar($value)) {
            $name = 'filters[' . $property . ']';

            return $this->hiddenInput($name, (string) $value);
        }
        if (\is_array($value)) {
            $out = '';
            foreach ($value as $subkey => $subvalue) {
                $name = 'filters[' . $property . '][' . $subkey . ']';
                if (\is_scalar($subvalue)) {
                    $out .= $this->hiddenInput($name, (string) $subvalue);
                }
            }

            return $out;
        }

        return '';
    }

    private function renderHiddenForKey(string $key, mixed $value): string
    {
        if (\is_scalar($value)) {
            return $this->hiddenInput($key, (string) $value);
        }
        if (\is_array($value)) {
            $out = '';
            foreach ($value as $subkey => $subvalue) {
                if (\is_scalar($subvalue)) {
                    $out .= $this->hiddenInput($key . '[' . $subkey . ']', (string) $subvalue);
                }
            }

            return $out;
        }

        return '';
    }

    private function hiddenInput(string $name, string $value): string
    {
        $nameEsc = htmlspecialchars($name, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $valueEsc = htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return \sprintf('<input type="hidden" name="%s" value="%s">', $nameEsc, $valueEsc);
    }
}
