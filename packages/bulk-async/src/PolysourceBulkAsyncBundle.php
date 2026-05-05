<?php

declare(strict_types=1);

namespace Polysource\BulkAsync;

use Polysource\BulkAsync\DependencyInjection\PolysourceBulkAsyncExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for `polysource/bulk-async`.
 *
 * Hosts register this in `config/bundles.php`:
 *
 *   Polysource\BulkAsync\PolysourceBulkAsyncBundle::class => ['all' => true],
 *
 * And import the routes in `config/routes.yaml`:
 *
 *   polysource_bulk_async:
 *       resource: '@PolysourceBulkAsyncBundle/Resources/config/routes.php'
 *       type: php
 *
 * The DI extension lives in {@see PolysourceBulkAsyncExtension} —
 * Doctrine-dependent services are gated on
 * `interface_exists(EntityManagerInterface)` and the Mercure
 * broadcaster on `class_exists(\Symfony\Component\Mercure\HubInterface)`
 * per ADR-024 §4 / §8.
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — the bundle
 * surfaces in `polysource:plugins:list`.
 */
#[AsPlugin(name: 'polysource/bulk-async', version: '0.1.0-alpha.1')]
final class PolysourceBulkAsyncBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceBulkAsyncExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
