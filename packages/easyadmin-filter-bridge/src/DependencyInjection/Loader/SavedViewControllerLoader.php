<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use Polysource\EasyAdminFilterBridge\EventListener\SavedViewApplySubscriber;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Default SavedViewController + `?view=<id>` subscriber. Together
 * they make the saved-views dropdown work end-to-end out of the box
 * — hosts can override by re-declaring the routes at higher
 * priority.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class SavedViewControllerLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return class_exists(\Polysource\Filter\SavedView\SavedViewService::class);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        // SavedViewController constructor type-hints
        // `Symfony\Bundle\SecurityBundle\Security`. The class ships
        // in `symfony/security-bundle` which is a `suggest` dep (not
        // required) — hosts may install the bridge on a kernel
        // without SecurityBundle (Sf 5.4 bridge-alone smoke path).
        // When SecurityBundle is absent we still register the
        // controller — but with autowiring OFF because Symfony's
        // autowire would resolve the Security type-hint via
        // reflection and fail on `class not found`. The constructor
        // signature uses `?Security` since v0.9.0, so the controller
        // is happy with a null arg; the handlers gate on
        // `$this->security?->getUser()`. v0.9.0 fix.
        $hasSecurityBundle = class_exists(\Symfony\Bundle\SecurityBundle\Security::class);
        $controllerDef = $container
            ->register(SavedViewController::class)
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;
        if ($hasSecurityBundle) {
            $controllerDef->setAutowired(true);
        } else {
            // Manual wiring: SavedViewService is the only required
            // arg in the constructor's positional order; ?Security,
            // ?CsrfTokenManagerInterface, and
            // ?SavedViewTeamResolverInterface all default to null and
            // the controller fails closed when they're unavailable
            // at request time.
            $controllerDef->setArguments([
                new Reference(\Polysource\Filter\SavedView\SavedViewService::class),
            ]);
        }

        $container
            ->register(SavedViewApplySubscriber::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;
    }
}
