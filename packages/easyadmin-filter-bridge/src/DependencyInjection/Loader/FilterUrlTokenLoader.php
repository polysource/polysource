<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Controller\FilterUrlTokenController;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FilterShortUrlExtension;
use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * FilterUrlToken (v0.5.0) — controller + Twig helpers for short
 * shareable filter URLs. Gated on the filter package's
 * FilterUrlTokenService being available (registered when Doctrine
 * is loaded).
 *
 * @internal
 *
 * @since 0.11.0
 */
final class FilterUrlTokenLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return class_exists(\Polysource\Filter\FilterUrlToken\FilterUrlTokenService::class)
            && interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(FilterUrlTokenController::class)
            ->setAutowired(true)
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;

        $container
            ->register(FilterShortUrlExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;
    }
}
