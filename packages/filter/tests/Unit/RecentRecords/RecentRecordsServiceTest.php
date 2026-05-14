<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\RecentRecords;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\RecentRecords\Model\RecentRecord;
use Polysource\Filter\RecentRecords\RecentRecordsService;
use Polysource\Filter\RecentRecords\Storage\InMemoryRecentRecordsStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(RecentRecordsService::class)]
#[CoversClass(InMemoryRecentRecordsStorage::class)]
#[CoversClass(RecentRecord::class)]
final class RecentRecordsServiceTest extends TestCase
{
    #[Test]
    public function recordsAViewAttributedToTheCurrentUser(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        $service = $this->makeService($storage, 'alice');

        $service->recordView('orders', '42', 'ORD-042', new DateTimeImmutable('2026-05-14T10:00:00'));

        $entries = $storage->recent('alice', 'orders', 10);
        self::assertCount(1, $entries);
        self::assertSame('ORD-042', $entries[0]->label);
        self::assertSame('42', $entries[0]->recordId);
    }

    #[Test]
    public function repeatedViewsUpsertRatherThanAppend(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        $service = $this->makeService($storage, 'alice');

        $service->recordView('orders', '42', 'Initial', new DateTimeImmutable('2026-05-14T10:00:00'));
        $service->recordView('orders', '42', 'Updated', new DateTimeImmutable('2026-05-14T11:00:00'));

        $entries = $storage->recent('alice', 'orders', 10);
        self::assertCount(1, $entries);
        self::assertSame('Updated', $entries[0]->label);
        self::assertEquals(new DateTimeImmutable('2026-05-14T11:00:00'), $entries[0]->viewedAt);
    }

    #[Test]
    public function recordViewIsNoopForAnonymousUsers(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        $service = new RecentRecordsService($storage, new TokenStorage());

        $service->recordView('orders', '42');

        self::assertSame([], $storage->recent('anyone', 'orders', 10));
    }

    #[Test]
    public function recentForCurrentUserOrdersMostRecentFirst(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        $service = $this->makeService($storage, 'alice');

        $service->recordView('orders', '1', 'First', new DateTimeImmutable('2026-05-14T10:00:00'));
        $service->recordView('orders', '2', 'Second', new DateTimeImmutable('2026-05-14T12:00:00'));
        $service->recordView('orders', '3', 'Third', new DateTimeImmutable('2026-05-14T11:00:00'));

        $entries = $service->recentForCurrentUser('orders');

        self::assertSame(['Second', 'Third', 'First'], array_column($entries, 'label'));
    }

    #[Test]
    public function recentForCurrentUserScopesByResource(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        $service = $this->makeService($storage, 'alice');

        $service->recordView('orders', '1');
        $service->recordView('products', '2');

        self::assertCount(1, $service->recentForCurrentUser('orders'));
        self::assertCount(1, $service->recentForCurrentUser('products'));
    }

    #[Test]
    public function recentForCurrentUserDoesNotLeakOtherUsersRecords(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        // Pre-seed an entry for Bob.
        $storage->upsert(new RecentRecord('bob', 'orders', '99', new DateTimeImmutable()));
        $service = $this->makeService($storage, 'alice');

        self::assertSame([], $service->recentForCurrentUser('orders'));
    }

    #[Test]
    public function recentForCurrentUserReturnsEmptyForAnonymous(): void
    {
        $storage = new InMemoryRecentRecordsStorage();
        $service = new RecentRecordsService($storage, new TokenStorage());

        self::assertSame([], $service->recentForCurrentUser('orders'));
    }

    #[Test]
    public function modelRejectsEmptyRecordId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RecentRecord('alice', 'orders', '', new DateTimeImmutable());
    }

    #[Test]
    public function modelRejectsEmptyStringLabel(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RecentRecord('alice', 'orders', '42', new DateTimeImmutable(), '');
    }

    private function makeService(InMemoryRecentRecordsStorage $storage, string $userId): RecentRecordsService
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($userId, null), 'main'));

        return new RecentRecordsService($storage, $tokenStorage);
    }
}
