<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger;

use Polysource\Adapter\Messenger\DependencyInjection\PolysourceMessengerExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle for the Polysource Messenger adapter.
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — discoverable via
 * `polysource:plugins:list`.
 */
#[AsPlugin(name: 'polysource/adapter-messenger')]
final class PolysourceMessengerBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceMessengerExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
