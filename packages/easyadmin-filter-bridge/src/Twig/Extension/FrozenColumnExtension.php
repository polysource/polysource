<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_frozen_column(side, offset)` —
 * emits the HTML attributes (class + inline `style`) that pin a
 * table column to the left or right edge of a scroll container
 * via `position: sticky`.
 *
 * Pure server-side: no JS, no external CSS to wire. Works in every
 * modern browser (sticky is universally supported since 2017).
 *
 * Per ADR-027 progressive enhancement: pinning falls back to a
 * normal column if the host disables the inline style via CSP —
 * the table renders fine, just without the freeze effect.
 *
 * Usage in a host EA index template override:
 *
 *     {# templates/bundles/EasyAdminBundle/crud/index.html.twig #}
 *     {% block table_head %}
 *         <tr>
 *             <th {{ polysource_frozen_column() }}>ID</th>     {# left, offset 0 #}
 *             <th {{ polysource_frozen_column('left', 60) }}>Name</th>
 *             …
 *             <th {{ polysource_frozen_column('right') }}>Actions</th>
 *         </tr>
 *     {% endblock %}
 *
 *     {% block table_body %}
 *         {% for entity in entities %}
 *             <tr>
 *                 <td {{ polysource_frozen_column() }}>{{ entity.id }}</td>
 *                 <td {{ polysource_frozen_column('left', 60) }}>{{ entity.name }}</td>
 *                 …
 *                 <td {{ polysource_frozen_column('right') }}>…</td>
 *             </tr>
 *         {% endfor %}
 *     {% endblock %}
 *
 * The wrapping table needs `overflow: auto` (EA's default
 * `.table-responsive` already does that — no extra config).
 *
 * The helper deliberately uses inline `style` rather than shipping
 * a stylesheet so the host doesn't have to wire a CSS asset
 * pipeline. Hosts who prefer CSS-only override the rules via
 * the `.polysource-frozen-column` class.
 *
 * @since 0.5.0
 */
final class FrozenColumnExtension extends AbstractExtension
{
    /**
     * Background colour applied to frozen cells so the table content
     * underneath doesn't bleed through. Uses Bootstrap 5's
     * `--bs-body-bg` CSS custom property with a `#fff` fallback —
     * works in both light and dark themes.
     */
    private const BACKGROUND = 'var(--bs-body-bg, #fff)';

    /**
     * Z-index applied to the sticky cell. Bootstrap 5's
     * `.table-sticky-top` uses 1; we go to 2 so a frozen column on
     * an EA index page sits above other sticky elements but well
     * below modals (z-index 1050+) and dropdowns (z-index 1000+).
     */
    private const Z_INDEX = 2;

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'polysource_frozen_column',
                $this->attrs(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * Build the `class="..." style="..."` attribute pair pinning a
     * table cell to the left or right edge of its scroll container.
     *
     * @param string $side   which edge to pin to: `'left'` or `'right'`
     *                       (any other value falls back to `'left'` so
     *                       a host typo doesn't break the table)
     * @param int    $offset distance from that edge in pixels
     *                       (use to stack multiple frozen columns)
     */
    public function attrs(string $side = 'left', int $offset = 0): Markup
    {
        $normalisedSide = 'right' === $side ? 'right' : 'left';
        $normalisedOffset = max(0, $offset);

        $style = \sprintf(
            'position: sticky; %s: %dpx; background-color: %s; z-index: %d;',
            $normalisedSide,
            $normalisedOffset,
            self::BACKGROUND,
            self::Z_INDEX,
        );

        $class = \sprintf(
            'polysource-frozen-column polysource-frozen-column--%s',
            $normalisedSide,
        );

        return new Markup(
            \sprintf('class="%s" style="%s"', $class, $style),
            'UTF-8',
        );
    }
}
