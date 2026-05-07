<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test kernel registering ActionDisplayedTestResource so the
 * ActionIsDisplayedTest can boot a real container and exercise the
 * full controller render path.
 */
final class ActionDisplayedTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(ActionDisplayedTestResource::class, ActionDisplayedTestResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/action-displayed/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/action-displayed/' . $this->environment . '/logs';
    }
}
