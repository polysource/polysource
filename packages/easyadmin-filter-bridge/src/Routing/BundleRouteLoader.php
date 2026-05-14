<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Routing;

use Polysource\EasyAdminFilterBridge\Controller\ColumnOrderController;
use Polysource\EasyAdminFilterBridge\Controller\ColumnPreferenceController;
use Polysource\EasyAdminFilterBridge\Controller\ExportController;
use Polysource\EasyAdminFilterBridge\Controller\FilterUrlTokenController;
use Polysource\EasyAdminFilterBridge\Controller\MatchingCountController;
use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Routing\Loader\AttributeClassLoader;
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
    ];

    public function loadAll(): RouteCollection
    {
        // phpcs:disable
        /** @phpstan-ignore-next-line class.implementsConfigureRoute */
        $loader = new class extends AttributeClassLoader {
            protected function configureRoute(\Symfony\Component\Routing\Route $route, ReflectionClass $class, ReflectionMethod $method, object $annot): void
            {
            }
        };
        // phpcs:enable

        $collection = new RouteCollection();
        foreach (self::CONTROLLERS as $class) {
            $collection->addCollection($loader->load($class));
        }

        return $collection;
    }
}
