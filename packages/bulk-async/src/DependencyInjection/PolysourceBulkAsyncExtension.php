<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
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
 *
 * `prepend()` registers the `assets/` dir as an AssetMapper path so
 * the Stimulus `progress_controller.js` is discoverable on the host
 * — without it the live-progress bar never connects to Mercure.
 */
final class PolysourceBulkAsyncExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!\is_array($bundles)) {
            return;
        }

        $assetsDir = \dirname(__DIR__, 2) . '/assets';

        if (
            \array_key_exists('FrameworkBundle', $bundles)
            && class_exists(\Symfony\Component\AssetMapper\AssetMapper::class)
        ) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $assetsDir => '@polysource/bulk-async',
                    ],
                ],
            ]);
        }
    }
}
