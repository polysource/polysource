<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Twig;

use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Security\SavedViewTeamResolverInterface;
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
        private readonly ?SavedViewTeamResolverInterface $teamResolver = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        // NOTE: `saved_views_dropdown` is NOT registered here.
        // Polysource's `symfony-bundle::PolysourceFilterExtension`
        // owns that function name (so templates using it always
        // parse, even when polysource/filter isn't installed) and
        // delegates rendering to this extension's `renderDropdown()`
        // when the host wires both bundles.
        //
        // Registering it here would collide with the symfony-bundle
        // stub at Twig boot. The bundle wiring of polysource/filter
        // injects this instance into PolysourceFilterExtension's
        // optional `?savedViewExtension` constructor argument.
        return [
            new TwigFunction(
                'polysource_route_exists',
                $this->routeExists(...),
            ),
            new TwigFunction(
                'polysource_team_scope_supported',
                $this->teamScopeSupported(...),
            ),
        ];
    }

    /**
     * Probe whether the host has wired a team resolver. Used by the
     * save-modal template to hide the TEAM scope option when no
     * resolver is registered — picking it would otherwise raise an
     * `InvalidArgumentException` from the SavedView constructor
     * because TEAM scope requires a non-empty teamId.
     */
    public function teamScopeSupported(): bool
    {
        return null !== $this->teamResolver;
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
