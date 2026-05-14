<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Twig;

use Polysource\Filter\SavedView\Model\SavedView;
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

    /**
     * Both `$service` and `$twig` are nullable so the extension can
     * register the `saved_views_dropdown` Twig function unconditionally
     * — templates parse on every host that has `polysource/filter`
     * installed, regardless of whether storage / security are wired.
     *
     * When either dependency is null (e.g. host has no DoctrineBundle
     * or no SecurityBundle), `renderDropdown()` returns an empty
     * string. The function is still callable; it just renders nothing.
     * This is the "no template-side gate needed" guarantee that the
     * v0.1.4 architecture provides.
     */
    public function __construct(
        private readonly ?SavedViewService $service = null,
        private readonly ?Environment $twig = null,
        private readonly ?RouterInterface $router = null,
        private readonly ?SavedViewTeamResolverInterface $teamResolver = null,
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
            new TwigFunction(
                'polysource_team_scope_supported',
                $this->teamScopeSupported(...),
            ),
            new TwigFunction(
                'polysource_active_saved_view',
                $this->activeSavedView(...),
            ),
        ];
    }

    /**
     * Resolve the SavedView active for the given resource — honours
     * `?view=<id>`, user personal default, then role default
     * (delegates to {@see SavedViewService::defaultFor}).
     *
     * Returns `null` when no view applies OR when the service isn't
     * wired (host without Doctrine + Security bundles) — templates
     * that depend on the active view (e.g. column widths) gracefully
     * degrade to the host's defaults.
     *
     * @since 0.5.1
     */
    public function activeSavedView(string $resourceName): ?SavedView
    {
        if (null === $this->service) {
            return null;
        }

        // Same defensive contract as renderDropdown(): a stale Doctrine
        // schema (e.g. host on a pre-v0.5.0 saved_view table without
        // `column_widths_json`) must NOT 500 every admin page render.
        // Surfaced 2026-05-14 by dogfooding round 3 (friction C3).
        try {
            return $this->service->defaultFor($resourceName);
        } catch (Throwable) {
            return null;
        }
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
        // Graceful degradation #1: when the host hasn't wired
        // a SavedView storage (no DoctrineBundle or no SecurityBundle),
        // the service is null. Render nothing rather than crashing —
        // templates that call `saved_views_dropdown()` unconditionally
        // still parse and the call site sees an empty string.
        if (null === $this->service || null === $this->twig) {
            return '';
        }

        // Graceful degradation #2: storage IS wired but the underlying
        // Doctrine schema is stale or missing. Common cases:
        //
        //  - Host installed the bundle but never ran polysource
        //    migrations → `saved_view` table doesn't exist.
        //  - Host on an older lineage of the schema (pre-v0.5.0)
        //    where `column_widths_json` doesn't exist yet.
        //  - Read-only replica without DDL parity.
        //
        // ANY Doctrine failure here would 500 every EA index page
        // in the host because the bridge's auto-prepended
        // `crud/index.html.twig` calls `saved_views_dropdown()`
        // unconditionally. Catch + degrade silently — the dropdown
        // disappears, the host's pages keep working, and the host
        // gets a chance to notice the migration gap on their own
        // schedule (cf. `doctrine:migrations:diff`).
        // Surfaced 2026-05-14 by dogfooding round 3 (friction C3).
        try {
            $views = $this->service->listVisible($resourceName);
            $current = $this->service->defaultFor($resourceName);
        } catch (Throwable) {
            return '';
        }

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
