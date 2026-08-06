<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * ColumnPreference wiring (v0.3.0, parallel to SavedView).
 *
 * The service pair is gated on DoctrineBundle + SecurityBundle: it
 * needs an EntityManager to persist + a TokenStorage to resolve the
 * current user. The Twig extension follows the same nullable-service
 * pattern as SavedViewExtension: always registered when TwigBundle
 * is loaded so templates parse on bridge-alone installs; the service
 * argument is null when storage isn't wired, in which case the
 * functions return safe defaults (false / []).
 *
 * @internal
 *
 * @since 0.11.0
 */
final class ColumnPreferenceLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return FeatureGate::hasTwigBundle($bundles) || self::storageAvailable($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $hasStorage = self::storageAvailable($bundles);

        if ($hasStorage) {
            $container
                ->register(\Polysource\Filter\ColumnPreference\Storage\DoctrineColumnPreferenceStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\ColumnPreference\Storage\ColumnPreferenceStorageInterface::class,
                \Polysource\Filter\ColumnPreference\Storage\DoctrineColumnPreferenceStorage::class,
            );
            $container
                ->register(\Polysource\Filter\ColumnPreference\ColumnPreferenceService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;
        }

        // ColumnPreferenceExtension — Twig functions
        // `polysource_column_hidden(...)` and `polysource_hidden_columns(...)`.
        if (FeatureGate::hasTwigBundle($bundles)) {
            $extensionDef = $container
                ->register(\Polysource\Filter\ColumnPreference\Twig\ColumnPreferenceExtension::class)
                ->addTag('twig.extension')
            ;
            if ($hasStorage) {
                $extensionDef->setAutowired(true);
            } else {
                $extensionDef->setArguments([null]);
            }
        }
    }

    private static function storageAvailable(mixed $bundles): bool
    {
        return interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
            && FeatureGate::hasSecurityBundle($bundles);
    }
}
