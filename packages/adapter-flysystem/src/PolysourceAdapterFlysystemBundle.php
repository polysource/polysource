<?php

declare(strict_types=1);

namespace Polysource\Adapter\Flysystem;

use Polysource\Adapter\Flysystem\DependencyInjection\PolysourceAdapterFlysystemExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

#[AsPlugin(name: 'polysource/adapter-flysystem', version: '0.1.0-alpha.1')]
final class PolysourceAdapterFlysystemBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceAdapterFlysystemExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
