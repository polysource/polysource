<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * FilterUrlToken wiring (v0.5.0). Doctrine-only — no Security
 * dependency: tokens are user-agnostic by design.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class FilterUrlTokenLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles);
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->register(\Polysource\Filter\FilterUrlToken\Storage\DoctrineFilterUrlTokenStorage::class)
            ->setAutowired(true)
        ;
        $container->setAlias(
            \Polysource\Filter\FilterUrlToken\Storage\FilterUrlTokenStorageInterface::class,
            \Polysource\Filter\FilterUrlToken\Storage\DoctrineFilterUrlTokenStorage::class,
        );
        $container
            ->register(\Polysource\Filter\FilterUrlToken\FilterUrlTokenService::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // Periodic purge command (v0.6.1) — hosts wire it to a
        // nightly cron, otherwise the polysource_filter_url_tokens
        // table grows unbounded.
        $container
            ->register(\Polysource\Filter\FilterUrlToken\Command\PurgeFilterUrlTokensCommand::class)
            ->setAutowired(true)
            ->addTag('console.command')
        ;
    }
}
