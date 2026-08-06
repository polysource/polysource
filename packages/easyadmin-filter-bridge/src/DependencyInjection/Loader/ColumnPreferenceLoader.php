<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Controller\ColumnOrderController;
use Polysource\EasyAdminFilterBridge\Controller\ColumnPreferenceController;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ColumnReorderExtension;
use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Column preference endpoints — visibility (v0.3.0) + reorder
 * (v0.5.0). Gated on the SAME conditions as the filter package's
 * ColumnPreferenceService registration: the class exists,
 * DoctrineBundle is loaded, SecurityBundle is loaded. Without those
 * the upstream service isn't registered and autowiring the
 * controllers would crash DI compile.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class ColumnPreferenceLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return class_exists(\Polysource\Filter\ColumnPreference\ColumnPreferenceService::class)
            && interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
            && FeatureGate::hasSecurityBundle($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(ColumnPreferenceController::class)
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;

        $container
            ->register(ColumnReorderExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        $container
            ->register(ColumnOrderController::class)
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;
    }
}
