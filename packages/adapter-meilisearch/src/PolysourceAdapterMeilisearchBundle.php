<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch;

use Polysource\Adapter\Meilisearch\DependencyInjection\PolysourceAdapterMeilisearchExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

#[AsPlugin(name: 'polysource/adapter-meilisearch', version: '0.1.0-alpha.1')]
final class PolysourceAdapterMeilisearchBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceAdapterMeilisearchExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
