<?php

declare(strict_types=1);

use Polysource\Adapter\Redis\Client\PredisRedisClient;
use Polysource\Adapter\Redis\Client\PredisRedisHashClient;
use Polysource\Adapter\Redis\Client\RedisClientInterface;
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
       implementing RedisClientInterface. */
    if (interface_exists(ClientInterface::class)) {
        $services->set(PredisRedisClient::class)
            ->arg('$client', service(ClientInterface::class)->nullOnInvalid());

        $services->alias(RedisClientInterface::class, PredisRedisClient::class);

        /* BC alias (v0.7 → v0.8) — host code that type-hinted on
           the old narrow interface still resolves. The hash-specific
           subclass is registered as its own service (the alias points
           there because PredisRedisClient does NOT implement
           RedisHashClientInterface — only PredisRedisHashClient does).
           Removed at v1.0 per the @deprecated marker. */
        $services->set(PredisRedisHashClient::class)
            ->arg('$client', service(ClientInterface::class)->nullOnInvalid());

        $services->alias(RedisHashClientInterface::class, PredisRedisHashClient::class);
    }

    /* No resource is auto-registered — hosts wire one Resource
       subclass per Redis namespace they want to admin, one per Redis
       data type (string, list, hash, set, zset). Same convention as
       adapter-doctrine. */
    unset($services);
};
