<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis;

use Polysource\Adapter\Redis\DependencyInjection\PolysourceAdapterRedisExtension;
use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for `polysource/adapter-redis`.
 *
 * Hosts register this in `config/bundles.php`:
 *
 *   Polysource\Adapter\Redis\PolysourceAdapterRedisBundle::class => ['all' => true],
 *
 * The adapter ships {@see DataSource\RedisHashDataSource} (generic
 * read+write over a namespaced Redis hash collection) and the
 * {@see Resource\RedisHashResource} convenience base. Hosts wire
 * one resource per Redis namespace they want to admin (feature
 * flags, lightweight config, session metadata, …).
 *
 * No Redis client is auto-registered — hosts supply their own
 * {@see Client\RedisHashClientInterface} implementation. The
 * package ships the {@see Client\PredisRedisHashClient} default
 * for hosts on Predis.
 *
 * Implements {@see AdminPluginInterface} per ADR-018.
 */
#[AsPlugin(name: 'polysource/adapter-redis')]
final class PolysourceAdapterRedisBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceAdapterRedisExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
