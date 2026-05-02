<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\Tests\Fixture\AlwaysDenyPermission;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * TestKernel variant where every permission attribute is denied. Used
 * by the 403-on-resource-access smoke test.
 */
final class DeniedTestKernel extends TestKernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests-denied/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests-denied/' . $this->environment . '/logs';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(AlwaysDenyPermission::class, AlwaysDenyPermission::class)
            ->setPublic(true)
        ;
        $container->setAlias(PermissionInterface::class, AlwaysDenyPermission::class)->setPublic(true);
    }
}
