<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Routing;

use Polysource\EasyAdminFilterBridge\Controller\ColumnOrderController;
use Polysource\EasyAdminFilterBridge\Controller\ColumnPreferenceController;
use Polysource\EasyAdminFilterBridge\Controller\ExportController;
use Polysource\EasyAdminFilterBridge\Controller\FilterUrlTokenController;
use Polysource\EasyAdminFilterBridge\Controller\MatchingCountController;
use Polysource\EasyAdminFilterBridge\Controller\RowDetailController;
use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Walks the bridge's `#[Route]`-attributed controllers and assembles
 * a single RouteCollection. Used by the Bundle's `boot()` hook to
 * splice the bridge's routes into the host router without requiring
 * a manual `config/routes.yaml` import.
 *
 * Same controller list as `Resources/config/routes.php` — keep them
 * in sync if you add or remove a controller. The duplication is the
 * cost of auto-loading without breaking the legacy host-import path.
 *
 * @since 0.5.4
 */
final class BundleRouteLoader
{
    /** @var list<class-string> Controller classes shipped with the bridge */
    private const CONTROLLERS = [
        SavedViewController::class,
        ColumnPreferenceController::class,
        ExportController::class,
        MatchingCountController::class,
        ColumnOrderController::class,
        FilterUrlTokenController::class,
        RowDetailController::class,
    ];

    public function loadAll(): RouteCollection
    {
        // Use Symfony's own framework-bundle AttributeRouteControllerLoader
        // which properly populates the `_controller` default via
        // `Class::method` strings. Our v0.5.4 prototype used a bare
        // AttributeClassLoader subclass with an empty `configureRoute`
        // — that loaded the URL pattern but left `_controller` unset,
        // so every route 404'd despite appearing in `debug:router`.
        // Surfaced 2026-05-14 by continued dogfooding round 2.
        $loader = new AttributeRouteControllerLoader();

        $collection = new RouteCollection();
        foreach (self::CONTROLLERS as $class) {
            $collection->addCollection($loader->load($class));
        }

        return $collection;
    }
}
