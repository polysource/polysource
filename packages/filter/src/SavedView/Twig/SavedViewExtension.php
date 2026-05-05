<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Twig;

use Polysource\Filter\SavedView\SavedViewService;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing `saved_views_dropdown(resourceName)` —
 * renders the dropdown of saved views visible to the current user
 * for the given resource, plus a "Save current as view" trigger.
 *
 * Usage:
 *
 * ```twig
 * {{ saved_views_dropdown('products') }}
 * ```
 *
 * The default template is `@PolysourceFilter/saved_view/dropdown.html.twig`.
 * Hosts override by aliasing `@PolysourceFilter` or by passing a
 * custom template path as the second argument.
 *
 * Per ADR-019 §7.
 *
 * @since 0.1.0
 */
final class SavedViewExtension extends AbstractExtension
{
    public const DEFAULT_TEMPLATE = '@PolysourceFilter/saved_view/dropdown.html.twig';

    public function __construct(
        private readonly SavedViewService $service,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'saved_views_dropdown',
                $this->renderDropdown(...),
                ['is_safe' => ['html']],
            ),
        ];
    }

    public function renderDropdown(
        string $resourceName,
        string $template = self::DEFAULT_TEMPLATE,
    ): string {
        $views = $this->service->listVisible($resourceName);
        $current = $this->service->defaultFor($resourceName);

        return $this->twig->render($template, [
            'resource_name' => $resourceName,
            'views' => $views,
            'current' => $current,
        ]);
    }
}
