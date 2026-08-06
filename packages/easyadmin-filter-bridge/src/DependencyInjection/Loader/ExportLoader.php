<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Controller\ExportController;
use Polysource\EasyAdminFilterBridge\Controller\MatchingCountController;
use Polysource\EasyAdminFilterBridge\Export\Exporter;
use Polysource\EasyAdminFilterBridge\Filter\UrlFilterApplier;
use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Streaming export + matching-count endpoints.
 *
 * The Exporter itself is stateless (no deps beyond the standard
 * lib; the XLSX backend self-gates on openspout availability at
 * runtime) and always registered. The HTTP endpoints —
 * ExportController + MatchingCountController and their shared
 * UrlFilterApplier (`?filters[...]` slice → Doctrine WHERE) — need
 * an EntityManager, so they're gated on DoctrineBundle inside
 * load(): hosts without Doctrine still get the Exporter service,
 * just no routes.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class ExportLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(Exporter::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        if (
            !interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            || !FeatureGate::hasDoctrineBundle($bundles)
        ) {
            return;
        }

        $container
            ->register(UrlFilterApplier::class)
            ->setAutowired(true)
            ->setPublic(false)
        ;

        $container
            ->register(ExportController::class)
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;

        $container
            ->register(MatchingCountController::class)
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;
    }
}
