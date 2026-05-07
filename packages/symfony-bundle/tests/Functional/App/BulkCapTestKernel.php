<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\Tests\Fixture\InMemoryCsrfTokenStorage;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BulkCapTestKernel extends TestKernel
{
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $container->register(BulkCapTestResource::class, BulkCapTestResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;

        // Lower the bulk cap so the test can exceed it without
        // having to send 500 ids in the request body.
        $container->setParameter('polysource.max_bulk_ids', 3);

        // Symfony's default `SessionTokenStorage` needs an active
        // request/session — awkward when calling
        // `CsrfTokenManager::getToken()` from a test before any
        // request has run. Swap to an in-memory fixture for the
        // test kernel; production keeps the session-backed default.
        $container->register(InMemoryCsrfTokenStorage::class, InMemoryCsrfTokenStorage::class)
            ->setPublic(true);
        $container->setAlias('security.csrf.token_storage', InMemoryCsrfTokenStorage::class)->setPublic(true);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/bulk-cap/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/bulk-cap/' . $this->environment . '/logs';
    }
}
