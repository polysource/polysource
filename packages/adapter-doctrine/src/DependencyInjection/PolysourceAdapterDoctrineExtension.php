<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the bundle's service definitions from
 * `Resources/config/services.php`.
 *
 * The package itself ships no auto-registered resource — hosts wire
 * one `DoctrineEntityResource` subclass per entity they want to
 * admin. The services file only registers the autowiring helpers
 * (so `DoctrineDataSource` can be `new`-ed inside a host service
 * with the EntityManager auto-injected).
 */
final class PolysourceAdapterDoctrineExtension extends Extension
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.php');
    }
}
