<?php

declare(strict_types=1);

namespace Polysource\Search\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class PolysourceSearchExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.php');
    }

    /**
     * Register the bundle's `assets/` directory as an AssetMapper path
     * so the Stimulus `cmdk_controller.js` is discoverable by Symfony's
     * StimulusBundle loader on the host app.
     *
     * Without this prepend, the Cmd+K palette markup mounts in the DOM
     * but the controller never connects (Stimulus never imports the
     * file because AssetMapper doesn't know about it), and the
     * keypress hooks for Cmd+K / Ctrl+K / "/" never fire.
     *
     * Mirrors {@see \Polysource\Filter\DependencyInjection\PolysourceFilterExtension::prepend()}.
     */
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
                        $assetsDir => '@polysource/search',
                    ],
                ],
            ]);
        }
    }
}
