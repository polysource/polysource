<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Kernel for the native row-details tests: RowDetailTestResource +
 * the per-record RecordSubjectPermission.
 */
final class RowDetailTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(RecordSubjectPermission::class, RecordSubjectPermission::class)
            ->setPublic(true)
        ;
        $container->setAlias(PermissionInterface::class, RecordSubjectPermission::class)->setPublic(true);

        $container->register(RowDetailTestResource::class, RowDetailTestResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;

        // Nested-listing fixtures (RowDetailNestedListingTest).
        foreach ([ParentOrdersResource::class, ChildItemsResource::class, SecretItemsResource::class] as $class) {
            $container->register($class, $class)
                ->setPublic(true)
                ->addTag('polysource.resource')
            ;
        }
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/row-detail/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/row-detail/' . $this->environment . '/logs';
    }
}
