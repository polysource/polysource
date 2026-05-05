<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* The package ships no auto-registered services beyond the
       autowiring defaults — hosts construct one DoctrineDataSource
       per entity inside their resource subclass:

           $services->set(App\Admin\ProductResource::class)
               ->arg('$em', service(EntityManagerInterface::class));

       Keeping this empty avoids opinionated assumptions (which
       entity? which filters? which permission?) that would break
       hosts that want to wire several resources off the same
       entity (e.g. "active products" + "archived products" with
       different filters). */
    unset($services);
};
