<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * RecentRecords wiring (v0.5.0). Needs Doctrine (storage) +
 * Security (per-user scoping).
 *
 * @internal
 *
 * @since 0.11.0
 */
final class RecentRecordsLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
            && FeatureGate::hasSecurityBundle($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(\Polysource\Filter\RecentRecords\Storage\DoctrineRecentRecordsStorage::class)
            ->setAutowired(true)
        ;
        $container->setAlias(
            \Polysource\Filter\RecentRecords\Storage\RecentRecordsStorageInterface::class,
            \Polysource\Filter\RecentRecords\Storage\DoctrineRecentRecordsStorage::class,
        );
        $container
            ->register(\Polysource\Filter\RecentRecords\RecentRecordsService::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;
    }
}
