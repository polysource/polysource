<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Polysource\Filter\SavedView\Model\SavedView;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing
 * `polysource_column_width_style(view, property)` — emits a
 * `style="width: Xpx"` attribute for a `<th>` or `<col>` element
 * based on the column-width override stored on a {@see SavedView}.
 *
 * Pure server-side: no JS, no client-side resize handles. The
 * widths are persisted on the saved view (introduced in v0.5.0)
 * and applied at render time via inline `style`. Hosts who want
 * client-side drag-to-resize ship their own controller and POST
 * the new widths back via the saved-view persistence layer.
 *
 * Per ADR-027 progressive enhancement: when the saved view
 * carries no width for a column (the common case), the helper
 * emits empty output — the column renders at the host's vanilla
 * width, no surprises.
 *
 * Usage in a host EA index template override:
 *
 *     {% set view = polysource_active_saved_view() %}  {# host-provided #}
 *
 *     <table>
 *         <colgroup>
 *             {% for column in view.columns %}
 *                 <col {{ polysource_column_width_style(view, column) }}>
 *             {% endfor %}
 *         </colgroup>
 *         …
 *     </table>
 *
 * @since 0.5.0
 */
final class ColumnWidthExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'polysource_column_width_style',
                $this->styleFor(...),
                ['is_safe' => ['html']],
            ),
            new TwigFunction(
                'polysource_column_width',
                $this->widthFor(...),
            ),
        ];
    }

    /**
     * Return the pixel width override for a column on a saved view,
     * or `null` when the column has no override (renders at the
     * host's default width).
     */
    public function widthFor(?SavedView $view, string $property): ?int
    {
        if (null === $view) {
            return null;
        }

        return $view->columnWidths[$property] ?? null;
    }

    /**
     * Build the `style="width: Xpx"` attribute string for a column.
     * Empty output when the view is null or the column has no
     * override — safe to splice into any template without
     * conditional wrapping.
     */
    public function styleFor(?SavedView $view, string $property): Markup
    {
        $width = $this->widthFor($view, $property);
        if (null === $width) {
            return new Markup('', 'UTF-8');
        }

        return new Markup(\sprintf('style="width: %dpx"', $width), 'UTF-8');
    }
}
