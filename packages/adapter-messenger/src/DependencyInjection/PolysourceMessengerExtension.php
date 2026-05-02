<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DependencyInjection;

use Polysource\Adapter\Messenger\Action\DismissFailedMessageAction;
use Polysource\Adapter\Messenger\Action\PurgeFailedMessagesAction;
use Polysource\Adapter\Messenger\Action\RetryAllFailedMessagesAction;
use Polysource\Adapter\Messenger\Action\RetryFailedMessageAction;
use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Wires the Messenger failed-transport-backed data source and resource.
 */
final class PolysourceMessengerExtension extends Extension
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__) . '/Resources/config'),
        );
        $loader->load('services.php');

        $failedTransport = $config['failed_transport_name'] ?? 'failed';
        \assert(\is_string($failedTransport));

        $resourceSlug = $config['resource_slug'] ?? 'failed-messages';
        \assert(\is_string($resourceSlug));

        $payloadMaxBytes = $config['payload_max_bytes'] ?? 50_000;
        \assert(\is_int($payloadMaxBytes));

        $maxRetryAll = $config['max_retry_all'] ?? 1000;
        \assert(\is_int($maxRetryAll));

        $maxPurge = $config['max_purge'] ?? 1000;
        \assert(\is_int($maxPurge));

        $container->setParameter('polysource_messenger.failed_transport_name', $failedTransport);
        $container->setParameter('polysource_messenger.resource_slug', $resourceSlug);
        $container->setParameter('polysource_messenger.payload_max_bytes', $payloadMaxBytes);
        $container->setParameter('polysource_messenger.max_retry_all', $maxRetryAll);
        $container->setParameter('polysource_messenger.max_purge', $maxPurge);

        // Logger is shared across the data source and every action.
        $loggerRefShared = new Reference(LoggerInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        // Inject the actual transport service into the data source.
        $container->getDefinition(MessengerFailedDataSource::class)
            ->replaceArgument(0, new Reference('messenger.transport.' . $failedTransport))
            ->replaceArgument(1, new Reference(EnvelopeMapper::class))
            ->replaceArgument(2, $loggerRefShared)
        ;

        $container->getDefinition(EnvelopeMapper::class)
            ->replaceArgument(0, '%polysource_messenger.payload_max_bytes%')
        ;

        // Wire the four actions exposed by FailedMessageResource:
        // bus + receiver references + shared logger.
        $busRef = new Reference(MessageBusInterface::class);
        $receiverRef = new Reference('messenger.transport.' . $failedTransport);

        $container->getDefinition(RetryFailedMessageAction::class)
            ->replaceArgument(0, $busRef)
            ->replaceArgument(1, $receiverRef)
            ->replaceArgument(2, $loggerRefShared)
        ;

        $container->getDefinition(DismissFailedMessageAction::class)
            ->replaceArgument(0, $receiverRef)
            ->replaceArgument(1, $loggerRefShared)
        ;

        $container->getDefinition(RetryAllFailedMessagesAction::class)
            ->replaceArgument(0, $busRef)
            ->replaceArgument(1, $receiverRef)
            ->replaceArgument(2, '%polysource_messenger.max_retry_all%')
            ->replaceArgument(3, $loggerRefShared)
        ;

        $container->getDefinition(PurgeFailedMessagesAction::class)
            ->replaceArgument(0, $receiverRef)
            ->replaceArgument(1, '%polysource_messenger.max_purge%')
            ->replaceArgument(2, $loggerRefShared)
        ;

        $container->getDefinition(FailedMessageResource::class)
            ->replaceArgument(0, new Reference(MessengerFailedDataSource::class))
            ->replaceArgument(1, '%polysource_messenger.resource_slug%')
            ->replaceArgument(2, [
                new Reference(RetryFailedMessageAction::class),
                new Reference(DismissFailedMessageAction::class),
                new Reference(RetryAllFailedMessagesAction::class),
                new Reference(PurgeFailedMessagesAction::class),
            ])
        ;
    }

    public function getAlias(): string
    {
        return 'polysource_messenger';
    }
}
