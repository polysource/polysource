<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\Tests\Fixture\AttributeMapPermission;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * TestKernel variant that registers the field-permission resource +
 * an `AttributeMapPermission` instead of the always-grant fixture.
 *
 * The map is mutable through the public service so individual test
 * cases can flip a permission on/off and assert the rendered table
 * accordingly. We keep a single kernel boot per test class to amortise
 * cache compilation.
 */
final class FieldPermissionTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        // Add the field-permission resource alongside the parent's
        // TestResource (kernel cache is per-class so this is isolated).
        $container->register(FieldPermissionTestResource::class, FieldPermissionTestResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;

        // Replace AlwaysGrant with the configurable per-attribute fixture.
        $container->register(AttributeMapPermission::class, AttributeMapPermission::class)
            ->setArguments([
                // First arg = map, second = default. SECRET defaults
                // to denied here; individual tests flip via
                // container::get(...)->setMap(...).
                ['SECRET' => false],
                true,
            ])
            ->setPublic(true);
        $container->setAlias(PermissionInterface::class, AttributeMapPermission::class)->setPublic(true);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/field-perm/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/field-perm/' . $this->environment . '/logs';
    }
}
