<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* No auto-registered resource — hosts wire one HttpResource per
       endpoint they want to admin (one resource = one URL +
       pagination strategy). The host's HttpClientInterface is
       auto-injected; for multi-tenant clients (one per upstream)
       hosts inject named scoped clients via #[Target]. */
    unset($services);
};
