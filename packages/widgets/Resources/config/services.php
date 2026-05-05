<?php

declare(strict_types=1);

use Polysource\Widgets\Dashboard\DashboardRegistry;
use Polysource\Widgets\Twig\DashboardExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(DashboardRegistry::class)
        ->arg('$dashboards', tagged_iterator('polysource.widgets.dashboard'))
        ->public();

    $services->set(DashboardExtension::class)
        ->arg('$twig', service('twig'))
        ->arg('$registry', service(DashboardRegistry::class));
};
