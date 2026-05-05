<?php

declare(strict_types=1);

namespace Polysource\Search;

use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Polysource\Search\DependencyInjection\PolysourceSearchExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for `polysource/search`.
 *
 * Hosts register this in `config/bundles.php`:
 *
 *   Polysource\Search\PolysourceSearchBundle::class => ['all' => true],
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — surfaces in
 * `polysource:plugins:list`.
 */
#[AsPlugin(name: 'polysource/search', version: '0.1.0-alpha.1')]
final class PolysourceSearchBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceSearchExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
