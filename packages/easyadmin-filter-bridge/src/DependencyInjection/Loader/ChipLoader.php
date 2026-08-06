<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ChipExtension;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Chip-rendering services: ChipValueFormatter resolves
 * boolean/entity values to human-readable strings; ChipExtension
 * exposes it to Twig as `polysource_chip_value()`.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class ChipLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(ChipValueFormatter::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        $container
            ->register(ChipExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;
    }
}
