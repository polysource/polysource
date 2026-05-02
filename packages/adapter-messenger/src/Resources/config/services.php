<?php

declare(strict_types=1);

use Polysource\Adapter\Messenger\Action\DismissFailedMessageAction;
use Polysource\Adapter\Messenger\Action\PurgeFailedMessagesAction;
use Polysource\Adapter\Messenger\Action\RetryAllFailedMessagesAction;
use Polysource\Adapter\Messenger\Action\RetryFailedMessageAction;
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
        ->args([null, null, null]) // transport + mapper + logger — replaced by extension
        ->tag('polysource.data_source')
    ;

    // Actions — bus + receiver + (optional) logger references replaced
    // by the extension. The logger argument is nullOnInvalid so a host
    // app without monolog still wires cleanly.
    $services
        ->set(RetryFailedMessageAction::class)
        ->args([null, null, null])
        ->tag('polysource.messenger.action')
    ;

    $services
        ->set(DismissFailedMessageAction::class)
        ->args([null, null])
        ->tag('polysource.messenger.action')
    ;

    $services
        ->set(RetryAllFailedMessagesAction::class)
        ->args([null, null, 1000, null])
        ->tag('polysource.messenger.action')
    ;

    $services
        ->set(PurgeFailedMessagesAction::class)
        ->args([null, 1000, null])
        ->tag('polysource.messenger.action')
    ;

    $services
        ->set(FailedMessageResource::class)
        ->args([
            null, // dataSource — replaced
            '',   // slug — replaced
            [],   // actions — replaced
        ])
        ->tag('polysource.resource')
    ;
};
