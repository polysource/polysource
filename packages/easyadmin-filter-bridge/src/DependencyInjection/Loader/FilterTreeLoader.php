<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\EventListener\FilterFormThemeRegistrationSubscriber;
use Polysource\EasyAdminFilterBridge\EventListener\FilterMarkerProcessor;
use Polysource\EasyAdminFilterBridge\EventListener\FilterSessionPersistenceSubscriber;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FilterTreeExtension;
use Polysource\EasyAdminFilterBridge\Twig\FilterTreeBuilder;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The filter modal's server-side machinery:
 *
 * - FilterTreeBuilder + FilterTreeExtension — the groups/tabs tree
 *   consumed by `crud/filters.html.twig` to render native
 *   `<details name="polysource-tab">` tabs (ADR-027, zero JS).
 * - FilterSessionPersistenceSubscriber — restores the previous
 *   filter slice per CRUD controller FQCN on BeforeCrudActionEvent.
 * - FilterFormThemeRegistrationSubscriber — form theme registration.
 * - FilterMarkerProcessor — walks FilterConfigDto, propagates
 *   `Polysource::tab/group` markers to subsequent filters, removes
 *   the markers.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class FilterTreeLoader implements FeatureLoaderInterface
{
    private const SERVICES = [
        FilterTreeBuilder::class,
        FilterTreeExtension::class,
        FilterSessionPersistenceSubscriber::class,
        FilterFormThemeRegistrationSubscriber::class,
        FilterMarkerProcessor::class,
    ];

    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        foreach (self::SERVICES as $class) {
            $container
                ->register($class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
            ;
        }
    }
}
