<?php

declare(strict_types=1);

namespace Polysource\Search\Twig;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing `polysource_search_palette()` — renders
 * the empty Cmd+K overlay HTML. The Stimulus
 * `polysource--search--cmdk` controller hydrates the overlay
 * client-side via fetch to `GET /admin/search?q=…`.
 *
 * Hosts include the helper once in their admin layout:
 *
 *     {{ polysource_search_palette() }}
 */
final class SearchExtension extends AbstractExtension
{
    public function __construct(private readonly Environment $twig)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_search_palette', $this->renderPalette(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderPalette(): string
    {
        return $this->twig->render('@PolysourceSearch/_palette.html.twig');
    }
}
