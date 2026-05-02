<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DependencyInjection;

use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

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

        $container->setParameter('polysource_messenger.failed_transport_name', $failedTransport);
        $container->setParameter('polysource_messenger.resource_slug', $resourceSlug);
        $container->setParameter('polysource_messenger.payload_max_bytes', $payloadMaxBytes);

        // Inject the actual transport service into the data source.
        $container->getDefinition(MessengerFailedDataSource::class)
            ->replaceArgument(0, new Reference('messenger.transport.' . $failedTransport))
            ->replaceArgument(1, new Reference(EnvelopeMapper::class))
        ;

        $container->getDefinition(EnvelopeMapper::class)
            ->replaceArgument(0, '%polysource_messenger.payload_max_bytes%')
        ;

        $container->getDefinition(FailedMessageResource::class)
            ->replaceArgument(0, new Reference(MessengerFailedDataSource::class))
            ->replaceArgument(1, '%polysource_messenger.resource_slug%')
        ;
    }

    public function getAlias(): string
    {
        return 'polysource_messenger';
    }
}
