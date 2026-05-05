<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* No auto-registered resource — hosts wire one FlysystemResource
       subclass per filesystem they want to admin (one per S3 bucket
       or local directory). The host's existing FilesystemOperator
       service is auto-injected via autowire. */
    unset($services);
};
