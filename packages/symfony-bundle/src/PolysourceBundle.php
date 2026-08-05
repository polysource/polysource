<?php

declare(strict_types=1);

namespace Polysource\Bundle;

use Polysource\Bundle\DependencyInjection\Compiler\PluginCompilerPass;
use Polysource\Bundle\DependencyInjection\PolysourceExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point.
 *
 * Auto-registered via Symfony Flex thanks to the `symfony-bundle` composer type.
 * Users may also register it manually in `config/bundles.php`.
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — every Polysource
 * package that ships as a Symfony bundle is itself a plugin.
 */
#[AsPlugin(name: 'polysource/symfony-bundle')]
final class PolysourceBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PluginCompilerPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
