<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Twig\Extension;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig extension exposing
 * `polysource_column_reorder_buttons(resource, property, columns)`
 * — renders a pair of ← → anchors per column header that POST
 * (well, GET — the token is in the query string for a single-step
 * UX) to the reorder controller.
 *
 * Per ADR-027 progressive enhancement: pure server-side baseline.
 * Click ← → → page reloads with the new order applied via the
 * persisted ColumnPreference override. Hosts who want HTML5
 * drag-and-drop layer their own Stimulus controller on top of
 * the same persistence backend (see docs).
 *
 * Usage in a host EA index template override:
 *
 *     {% block table_head %}
 *         <tr>
 *         {% for column in columns %}
 *             <th>
 *                 {{ column.label }}
 *                 {{ polysource_column_reorder_buttons(
 *                     resource,
 *                     column.property,
 *                     columns|map(c => c.property)|list,
 *                 ) }}
 *             </th>
 *         {% endfor %}
 *         </tr>
 *     {% endblock %}
 *
 * @since 0.5.0
 */
final class ColumnReorderExtension extends AbstractExtension
{
    use TranslatorFallbackTrait;

    public function __construct(
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
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
                'polysource_column_reorder_buttons',
                $this->renderButtons(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    /**
     * @param list<string> $columns the full ordered column list — used
     *                              to compute whether the property is
     *                              already first / last (renders the
     *                              corresponding button as disabled)
     */
    public function renderButtons(string $resource, string $property, array $columns): Markup
    {
        if (null === $this->urlGenerator || null === $this->csrfTokenManager) {
            // Bridge not wired — emit empty markup rather than crash.
            // The DI registration gates the service on these deps;
            // this guard is here for unit-test flexibility.
            return new Markup('', 'UTF-8');
        }

        $position = array_search($property, $columns, true);
        $isFirst = 0 === $position;
        $isLast = false !== $position && $position === \count($columns) - 1;

        $token = $this->csrfTokenManager->getToken('polysource_column_order_' . $resource)->getValue();

        $leftUrl = $this->urlForMove($resource, $property, 'left', $columns, $token);
        $rightUrl = $this->urlForMove($resource, $property, 'right', $columns, $token);

        $leftAttrs = $isFirst
            ? 'class="btn btn-sm btn-link disabled" aria-disabled="true" tabindex="-1"'
            : 'class="btn btn-sm btn-link"';
        $rightAttrs = $isLast
            ? 'class="btn btn-sm btn-link disabled" aria-disabled="true" tabindex="-1"'
            : 'class="btn btn-sm btn-link"';

        $leftEscaped = htmlspecialchars($leftUrl, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $rightEscaped = htmlspecialchars($rightUrl, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        $esc = static fn (string $v): string => htmlspecialchars($v, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $textGroup = $esc($this->transWithFallback('polysource.columns.reorder', 'Reorder column'));
        $textLeft = $esc($this->transWithFallback('polysource.columns.move_left', 'Move left'));
        $textRight = $esc($this->transWithFallback('polysource.columns.move_right', 'Move right'));

        $html = <<<HTML
            <span class="polysource-column-reorder" role="group" aria-label="{$textGroup}">
                <a href="{$leftEscaped}" {$leftAttrs} aria-label="{$textLeft}">←</a>
                <a href="{$rightEscaped}" {$rightAttrs} aria-label="{$textRight}">→</a>
            </span>
            HTML;

        return new Markup($html, 'UTF-8');
    }

    /**
     * @param list<string> $columns
     */
    private function urlForMove(
        string $resource,
        string $property,
        string $direction,
        array $columns,
        string $token,
    ): string {
        \assert(null !== $this->urlGenerator);

        return $this->urlGenerator->generate(
            'polysource_column_order_move',
            [
                'resource' => $resource,
                'property' => $property,
                'direction' => $direction,
                'columns' => $columns,
                '_token' => $token,
            ],
        );
    }
}
