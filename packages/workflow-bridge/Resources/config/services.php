<?php

declare(strict_types=1);

use Polysource\WorkflowBridge\Action\TransitionActionFactory;
use Polysource\WorkflowBridge\Service\TransitionDiscovery;
use Polysource\WorkflowBridge\Service\WorkflowResolver;
use Polysource\WorkflowBridge\Twig\WorkflowChipExtension;
use Polysource\WorkflowBridge\Twig\WorkflowChipPalette;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    // Bundle is gated at composer level (symfony/workflow is a hard
    // require). The defensive class_exists check exists only for
    // monorepo development scenarios where the workflow component
    // hasn't been installed in vendor/ yet.
    if (!class_exists(Workflow::class)) {
        return;
    }

    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* ---------------------------------------------------------------
     * Resolution / discovery
     * --------------------------------------------------------------- */
    $services->set(WorkflowResolver::class)
        ->arg('$registry', service(Registry::class));

    $services->set(TransitionDiscovery::class)
        ->arg('$resolver', service(WorkflowResolver::class));

    $services->set(TransitionActionFactory::class)
        ->arg('$resolver', service(WorkflowResolver::class))
        ->arg('$discovery', service(TransitionDiscovery::class))
        ->public();

    /* ---------------------------------------------------------------
     * State chip palette + Twig extension
     *
     * `$palettes` is overridden by PolysourceWorkflowBridgeExtension
     * with the resolved bundle config map. We declare an empty
     * default so the service can still be instantiated when the
     * host doesn't ship a `polysource_workflow_bridge` config block.
     * --------------------------------------------------------------- */
    $services->set(WorkflowChipPalette::class)
        ->arg('$palettes', []);

    $services->set(WorkflowChipExtension::class)
        ->arg('$palette', service(WorkflowChipPalette::class));
};
