<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * BulkActionHistory wiring (v0.5.0). Same gating as
 * ColumnPreference: needs Doctrine + Security.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class BulkActionHistoryLoader implements FeatureLoaderInterface
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
            ->register(\Polysource\Filter\BulkActionHistory\Storage\DoctrineBulkActionHistoryStorage::class)
            ->setAutowired(true)
        ;
        $container->setAlias(
            \Polysource\Filter\BulkActionHistory\Storage\BulkActionHistoryStorageInterface::class,
            \Polysource\Filter\BulkActionHistory\Storage\DoctrineBulkActionHistoryStorage::class,
        );
        $container
            ->register(\Polysource\Filter\BulkActionHistory\BulkActionHistoryService::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // Periodic purge command (v0.6.1) — opt-in via cron in
        // non-regulated hosts. Compliance hosts MUST NOT run it
        // (cf. docblock).
        $container
            ->register(\Polysource\Filter\BulkActionHistory\Command\PurgeBulkActionHistoryCommand::class)
            ->setAutowired(true)
            ->addTag('console.command')
        ;
    }
}
