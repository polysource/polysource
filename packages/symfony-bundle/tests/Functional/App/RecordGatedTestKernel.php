<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\Tests\Fixture\InMemoryCsrfTokenStorage;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test kernel wiring {@see RecordGatedTestResource} with the
 * per-record {@see RecordSubjectPermission} so the
 * RecordAwareActionGatingTest can exercise voter-subject and
 * isDisplayed()-context gating through real HTTP renders.
 */
final class RecordGatedTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(RecordSubjectPermission::class, RecordSubjectPermission::class)
            ->setPublic(true)
        ;
        $container->setAlias(PermissionInterface::class, RecordSubjectPermission::class)->setPublic(true);

        $container->register(RecordGatedTestResource::class, RecordGatedTestResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;

        // Same trick as BulkCapTestKernel: the session-backed CSRF
        // storage can't mint a token outside a request, so tests use
        // the in-memory fixture storage.
        $container->register(InMemoryCsrfTokenStorage::class, InMemoryCsrfTokenStorage::class)
            ->setPublic(true);
        $container->setAlias('security.csrf.token_storage', InMemoryCsrfTokenStorage::class)->setPublic(true);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/record-gated/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/record-gated/' . $this->environment . '/logs';
    }
}
