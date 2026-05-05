<?php

declare(strict_types=1);

use Polysource\Adapter\Redis\Client\PredisRedisHashClient;
use Polysource\Adapter\Redis\Client\RedisHashClientInterface;
use Predis\ClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    /* Predis-backed default client — registered only when the host
       has Predis installed AND a `Predis\ClientInterface` already
       wired in DI (typically via SncRedisBundle or a manual service
       declaration). Hosts on ext-redis ship their own adapter
       implementing RedisHashClientInterface. */
    if (interface_exists(ClientInterface::class)) {
        $services->set(PredisRedisHashClient::class)
            ->arg('$client', service(ClientInterface::class)->nullOnInvalid());

        $services->alias(RedisHashClientInterface::class, PredisRedisHashClient::class);
    }

    /* No resource is auto-registered — hosts wire one
       RedisHashResource subclass per Redis namespace they want to
       admin. Same convention as adapter-doctrine. */
    unset($services);
};
