<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing a 2-state row density toggle —
 * `compact` (Bootstrap's `table-sm`) vs. `normal` (Bootstrap's
 * default `.table` spacing).
 *
 * Server-side only: the current density is read from the URL
 * query string (`?density=compact|normal`). The toggle itself
 * is a pair of `<a href>` anchors — no JS, no form submission,
 * no cookies. Hosts who want session-level persistence wire a
 * RequestListener similar to `FilterSessionPersistenceSubscriber`.
 *
 * Per ADR-027 progressive enhancement — works without any
 * client-side scripting; clicking a button reloads the listing
 * with the new density slice applied via Twig class output.
 *
 * Usage in a host EA index template override:
 *
 *     <table class="table {{ polysource_row_density_class() }}">
 *         …
 *     </table>
 *
 *     {{ polysource_row_density_toggle() }}
 *
 * The toggle preserves every other query parameter (filters,
 * sort, page, search) by reading the current request's full query
 * string and replacing only the `density` key.
 *
 * @since 0.5.0
 */
final class RowDensityExtension extends AbstractExtension
{
    /** @var list<string> the two supported density modes */
    private const DENSITIES = ['compact', 'normal'];

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
                'polysource_row_density_class',
                $this->tableClass(...),
            ),
            new TwigFunction(
                'polysource_row_density_current',
                $this->currentDensity(...),
            ),
            new TwigFunction(
                'polysource_row_density_toggle',
                $this->renderToggle(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * Read the current density from the URL query string. Defaults
     * to `'normal'`; unknown values fall back to `'normal'`.
     */
    public function currentDensity(): string
    {
        $value = $this->requestStack?->getCurrentRequest()?->query->get('density');
        $stringValue = \is_string($value) ? $value : '';

        return \in_array($stringValue, self::DENSITIES, true) ? $stringValue : 'normal';
    }

    /**
     * Return the Bootstrap class to splice into the `<table>` element.
     * `'table-sm'` for compact, empty for normal (the default `.table`
     * spacing).
     */
    public function tableClass(): string
    {
        return 'compact' === $this->currentDensity() ? 'table-sm' : '';
    }

    /**
     * Render the 2-state toggle as a Bootstrap `.btn-group`. Each
     * button is a plain anchor — the link points at the current
     * URL with `?density=X` replacing the existing density slice.
     */
    public function renderToggle(): Markup
    {
        $current = $this->currentDensity();

        $normalUrl = $this->urlForDensity('normal');
        $compactUrl = $this->urlForDensity('compact');

        $normalActive = 'normal' === $current;
        $compactActive = 'compact' === $current;

        $normalClass = $normalActive ? 'btn btn-secondary active' : 'btn btn-outline-secondary';
        $compactClass = $compactActive ? 'btn btn-secondary active' : 'btn btn-outline-secondary';

        $normalAria = $normalActive ? 'true' : 'false';
        $compactAria = $compactActive ? 'true' : 'false';

        $html = <<<HTML
            <div class="btn-group btn-group-sm polysource-row-density-toggle" role="group" aria-label="Row density">
                <a href="{$normalUrl}" class="{$normalClass}" aria-pressed="{$normalAria}">Normal</a>
                <a href="{$compactUrl}" class="{$compactClass}" aria-pressed="{$compactAria}">Compact</a>
            </div>
            HTML;

        return new Markup($html, 'UTF-8');
    }

    /**
     * Build the URL for a given density value, preserving every
     * other query parameter from the current request.
     */
    private function urlForDensity(string $density): string
    {
        $request = $this->requestStack?->getCurrentRequest();
        $path = null !== $request ? $request->getPathInfo() : '';
        /** @var array<string, mixed> $query */
        $query = null !== $request ? $request->query->all() : [];
        $query['density'] = $density;

        $qs = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        return '' !== $qs ? $path . '?' . $qs : $path;
    }
}
