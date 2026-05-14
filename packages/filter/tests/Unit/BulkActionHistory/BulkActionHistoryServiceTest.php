<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\BulkActionHistory;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\BulkActionHistory\BulkActionHistoryService;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;
use Polysource\Filter\BulkActionHistory\Storage\InMemoryBulkActionHistoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(BulkActionHistoryService::class)]
#[CoversClass(InMemoryBulkActionHistoryStorage::class)]
#[CoversClass(BulkActionEntry::class)]
final class BulkActionHistoryServiceTest extends TestCase
{
    #[Test]
    public function recordsAnEntryWithTheCurrentUserAsOwner(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $service = $this->makeService($storage, 'alice');

        $service->record(
            id: 'entry-1',
            resourceName: 'orders',
            actionName: 'archive',
            affectedCount: 7,
            metadata: ['filter' => 'status=draft'],
            occurredAt: new DateTimeImmutable('2026-05-14T10:00:00'),
        );

        $entries = $storage->recent('orders', 'alice', 10);
        self::assertCount(1, $entries);
        self::assertSame('alice', $entries[0]->ownerId);
        self::assertSame('archive', $entries[0]->actionName);
        self::assertSame(7, $entries[0]->affectedCount);
        self::assertSame(['filter' => 'status=draft'], $entries[0]->metadata);
    }

    #[Test]
    public function recordsNoEntryForAnonymousUsers(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $service = new BulkActionHistoryService($storage, new TokenStorage());

        $service->record('entry-1', 'orders', 'archive', 3);

        self::assertSame([], $storage->recent(null, null, 10));
    }

    #[Test]
    public function recentForCurrentUserReturnsOnlyTheCurrentUsersEntries(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $storage->append($this->makeEntry('e1', 'alice', 'orders', 'archive', new DateTimeImmutable('2026-05-14T10:00:00')));
        $storage->append($this->makeEntry('e2', 'bob', 'orders', 'archive', new DateTimeImmutable('2026-05-14T11:00:00')));
        $storage->append($this->makeEntry('e3', 'alice', 'orders', 'mark_paid', new DateTimeImmutable('2026-05-14T12:00:00')));

        $service = $this->makeService($storage, 'alice');
        $entries = $service->recentForCurrentUser('orders');

        self::assertCount(2, $entries);
        self::assertSame('mark_paid', $entries[0]->actionName);
        self::assertSame('archive', $entries[1]->actionName);
    }

    #[Test]
    public function recentForResourceReturnsAllUsersEntriesForAdmin(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $storage->append($this->makeEntry('e1', 'alice', 'orders', 'archive', new DateTimeImmutable('2026-05-14T10:00:00')));
        $storage->append($this->makeEntry('e2', 'bob', 'orders', 'archive', new DateTimeImmutable('2026-05-14T11:00:00')));

        $service = $this->makeService($storage, 'admin');
        $entries = $service->recentForResource('orders');

        self::assertCount(2, $entries);
    }

    #[Test]
    public function recentForCurrentUserReturnsEmptyForAnonymous(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $service = new BulkActionHistoryService($storage, new TokenStorage());

        self::assertSame([], $service->recentForCurrentUser('orders'));
    }

    #[Test]
    public function modelRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BulkActionEntry('', 'alice', 'orders', 'archive', 0, new DateTimeImmutable());
    }

    #[Test]
    public function modelRejectsNegativeAffectedCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BulkActionEntry('e1', 'alice', 'orders', 'archive', -1, new DateTimeImmutable());
    }

    private function makeService(InMemoryBulkActionHistoryStorage $storage, string $userId): BulkActionHistoryService
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser($userId, null), 'main'));

        return new BulkActionHistoryService($storage, $tokenStorage);
    }

    private function makeEntry(
        string $id,
        string $ownerId,
        string $resourceName,
        string $actionName,
        DateTimeImmutable $occurredAt,
    ): BulkActionEntry {
        return new BulkActionEntry(
            id: $id,
            ownerId: $ownerId,
            resourceName: $resourceName,
            actionName: $actionName,
            affectedCount: 1,
            occurredAt: $occurredAt,
        );
    }
}
