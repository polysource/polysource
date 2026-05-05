<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the bundle's service definitions from
 * `Resources/config/services.php`.
 *
 * Doctrine + Mercure-dependent services are gated inside
 * `services.php` itself (cf. ADR-024 §4 / §8) so this extension
 * stays minimal and hosts can override pieces without re-implementing
 * the whole loader.
 */
final class PolysourceBulkAsyncExtension extends Extension
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
