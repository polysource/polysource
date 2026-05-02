<?php

declare(strict_types=1);

use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire(false)
            ->autoconfigure(false)
            ->private();

    $services
        ->set(EnvelopeMapper::class)
        ->args([0]) // payload_max_bytes — replaced by extension
    ;

    $services
        ->set(MessengerFailedDataSource::class)
        ->args([null, null]) // transport + mapper — replaced by extension
        ->tag('polysource.data_source')
    ;

    $services
        ->set(FailedMessageResource::class)
        ->args([null, '']) // dataSource + slug — replaced by extension
        ->tag('polysource.resource')
    ;
};
