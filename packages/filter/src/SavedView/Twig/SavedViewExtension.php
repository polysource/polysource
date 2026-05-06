<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Twig;

use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing:
 *  - `saved_views_dropdown(resourceName)` — renders the dropdown of
 *    saved views visible to the current user for the given resource,
 *    plus a "Save current as view" trigger.
 *  - `polysource_route_exists(name)` — boolean helper used by the
 *    bundled save-modal template to keep a sane fallback when the
 *    host hasn't wired the create/delete routes yet.
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
        private readonly ?RouterInterface $router = null,
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
            new TwigFunction(
                'polysource_route_exists',
                $this->routeExists(...),
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

    /**
     * Probe whether a Symfony route is registered. Used by the bundled
     * save-modal Twig to gracefully degrade when the host hasn't wired
     * `polysource_saved_view_create` to a controller — the modal still
     * renders (no crash) but the form action stays `#` so the user
     * doesn't get a hard error trying to save.
     */
    public function routeExists(string $name): bool
    {
        if ($this->router === null) {
            return false;
        }

        try {
            $this->router->generate($name, ['resource' => '_probe_']);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        } catch (Throwable) {
            // The route exists but a required parameter is missing.
            // That counts as "exists" for our purposes — the host has
            // wired it, the missing param is the caller's job.
            return true;
        }
    }
}
