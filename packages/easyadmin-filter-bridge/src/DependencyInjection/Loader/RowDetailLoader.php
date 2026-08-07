<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Controller\RowDetailController;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailProviderInterface;
use Polysource\EasyAdminFilterBridge\RowDetail\RowDetailRegistry;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowDetailExtension;
use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Expandable row details (v1.1.0): provider autoconfiguration +
 * registry + chevron Twig gate always load (they're inert without
 * providers); the lazy endpoint controller loads only on a
 * Doctrine-wired kernel, like the export/matching-count endpoints.
 *
 * The `?AuthorizationCheckerInterface` collaborators resolve to the
 * real checker when SecurityBundle is wired and to `null` otherwise
 * — consumers fail closed when a provider declares a permission
 * attribute (cf. RowDetailExtension / RowDetailController).
 *
 * @internal
 *
 * @since 1.1.0
 */
final class RowDetailLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        $container
            ->registerForAutoconfiguration(RowDetailProviderInterface::class)
            ->addTag('polysource.row_detail_provider')
        ;

        $container
            ->register(RowDetailRegistry::class)
            ->setArguments([new TaggedIteratorArgument('polysource.row_detail_provider')])
            ->setPublic(true)
        ;

        $authChecker = new Reference(
            'security.authorization_checker',
            ContainerInterface::NULL_ON_INVALID_REFERENCE,
        );

        $container
            ->register(RowDetailExtension::class)
            ->setArguments([new Reference(RowDetailRegistry::class), $authChecker])
            ->addTag('twig.extension')
        ;

        if (
            !interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            || !FeatureGate::hasDoctrineBundle($bundles)
        ) {
            return;
        }

        $container
            ->register(RowDetailController::class)
            ->setArguments([
                new Reference(RowDetailRegistry::class),
                new Reference(\Doctrine\ORM\EntityManagerInterface::class),
                new Reference('twig'),
                $authChecker,
                // The bundle's embedded-listing renderer, when
                // polysource/symfony-bundle is installed alongside the
                // bridge — unlocks RowDetail::listing(). Resolves to
                // null on bridge-alone hosts.
                new Reference(
                    'Polysource\Bundle\RowDetail\EmbeddedListingRenderer',
                    ContainerInterface::NULL_ON_INVALID_REFERENCE,
                ),
            ])
            ->setPublic(true)
            ->addTag('controller.service_arguments')
        ;
    }
}
