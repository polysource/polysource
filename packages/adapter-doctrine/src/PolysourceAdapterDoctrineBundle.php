<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine;

use Polysource\Adapter\Doctrine\DependencyInjection\PolysourceAdapterDoctrineExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for `polysource/adapter-doctrine`.
 *
 * Hosts register this in `config/bundles.php`:
 *
 *   Polysource\Adapter\Doctrine\PolysourceAdapterDoctrineBundle::class => ['all' => true],
 *
 * The adapter ships {@see DataSource\DoctrineDataSource} (generic
 * read+write over any mapped entity) and the
 * {@see Resource\DoctrineEntityResource} convenience base. Hosts
 * subclass the resource per entity they want to admin.
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — surfaces in
 * `polysource:plugins:list` alongside the other adapters.
 *
 * Per ADR-012 this is the *cohabitation* case: hosts run EasyAdmin
 * on some entities and Polysource on others (e.g. Messenger failed
 * messages + Doctrine product catalogue) inside the same admin
 * panel.
 */
#[AsPlugin(name: 'polysource/adapter-doctrine')]
final class PolysourceAdapterDoctrineBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceAdapterDoctrineExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
