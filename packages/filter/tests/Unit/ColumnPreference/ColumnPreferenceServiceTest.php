<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\ColumnPreference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\ColumnPreference\ColumnPreferenceService;
use Polysource\Filter\ColumnPreference\Storage\InMemoryColumnPreferenceStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(ColumnPreferenceService::class)]
final class ColumnPreferenceServiceTest extends TestCase
{
    #[Test]
    public function findReturnsNullWhenAnonymous(): void
    {
        $service = $this->makeService(authenticatedUser: null);

        self::assertNull($service->findForCurrentUser('orders'));
        self::assertSame([], $service->hiddenColumns('orders'));
    }

    #[Test]
    public function findReturnsTheSavedPreferenceForTheCurrentUser(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $storage->save(new \Polysource\Filter\ColumnPreference\Model\ColumnPreference(
            ownerId: 'alice',
            resourceName: 'orders',
            hiddenColumns: ['paidAt', 'shippedAt'],
        ));

        $service = $this->makeService(authenticatedUser: 'alice', storage: $storage);

        $pref = $service->findForCurrentUser('orders');
        self::assertNotNull($pref);
        self::assertSame(['paidAt', 'shippedAt'], $pref->hiddenColumns);
        self::assertSame(['paidAt', 'shippedAt'], $service->hiddenColumns('orders'));
    }

    #[Test]
    public function setHiddenColumnsPersistsForCurrentUser(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $service = $this->makeService(authenticatedUser: 'alice', storage: $storage);

        $service->setHiddenColumns('orders', ['paidAt', 'shippedAt', 'paidAt']);

        $stored = $storage->find('alice', 'orders');
        self::assertNotNull($stored);
        // Deduplicated
        self::assertSame(['paidAt', 'shippedAt'], $stored->hiddenColumns);
    }

    #[Test]
    public function setHiddenColumnsIsNoopWhenAnonymous(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $service = $this->makeService(authenticatedUser: null, storage: $storage);

        $service->setHiddenColumns('orders', ['paidAt']);

        self::assertNull($storage->find('alice', 'orders'));
    }

    #[Test]
    public function resetDeletesTheUsersPreference(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $storage->save(new \Polysource\Filter\ColumnPreference\Model\ColumnPreference(
            ownerId: 'alice',
            resourceName: 'orders',
            hiddenColumns: ['paidAt'],
        ));
        $service = $this->makeService(authenticatedUser: 'alice', storage: $storage);

        $service->reset('orders');

        self::assertNull($storage->find('alice', 'orders'));
    }

    private function makeService(
        ?string $authenticatedUser,
        ?InMemoryColumnPreferenceStorage $storage = null,
    ): ColumnPreferenceService {
        $tokenStorage = new TokenStorage();
        if (null !== $authenticatedUser) {
            $user = new InMemoryUser($authenticatedUser, null, ['ROLE_USER']);
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        }

        return new ColumnPreferenceService(
            $storage ?? new InMemoryColumnPreferenceStorage(),
            $tokenStorage,
        );
    }
}
