<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Polysource\Filter\Twig\Extension\FilterTagsExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * FilterTagsExtension — Twig function `filter_tags()`. Registered
 * explicitly + tagged `twig.extension` because the bundle ships its
 * Twig extension (host apps can't autowire foreign-bundle services,
 * and we don't ship a services.xml).
 *
 * Guarded on TwigBundle presence so non-Twig hosts (rare, but
 * possible: a console-only app, a unit test kernel) don't get an
 * unused service that fails to autowire `Twig\Environment`.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class FilterTagsLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return FeatureGate::hasTwigBundle($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(FilterTagsExtension::class)
            ->setAutowired(true)
            ->addTag('twig.extension')
        ;
    }
}
