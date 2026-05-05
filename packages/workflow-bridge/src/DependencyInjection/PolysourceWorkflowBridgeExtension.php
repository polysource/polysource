<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\DependencyInjection;

use Polysource\WorkflowBridge\Twig\WorkflowChipPalette;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads the bundle's service definitions from
 * `Resources/config/services.php` and wires the configured palette
 * map into {@see WorkflowChipPalette}.
 */
final class PolysourceWorkflowBridgeExtension extends Extension
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.php');

        /** @var array<string, array<string, string>> $palettes */
        $palettes = \is_array($config['palettes'] ?? null) ? $config['palettes'] : [];

        $container->getDefinition(WorkflowChipPalette::class)
            ->setArgument('$palettes', $palettes);
    }
}
