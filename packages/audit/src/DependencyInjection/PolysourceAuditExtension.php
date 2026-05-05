<?php

declare(strict_types=1);

namespace Polysource\Audit\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the bundle's service definitions from
 * `Resources/config/services.php`.
 *
 * The Doctrine-dependent services (storage adapter, audit log
 * resource) are gated inside `services.php` itself via
 * `interface_exists(EntityManagerInterface)` — keeps this extension
 * minimal and lets hosts override pieces without re-implementing
 * the whole loader.
 */
final class PolysourceAuditExtension extends Extension
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
