<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* No auto-registered resource — hosts wire one
       MeilisearchIndexResource subclass per index they want to admin
       (one resource = one index). Hosts construct the
       MeilisearchIndexInterface implementation for that index inside
       their own DI; this package doesn't assume which client they
       use (official meilisearch-php, custom HTTP wrapper, …). */
    unset($services);
};
