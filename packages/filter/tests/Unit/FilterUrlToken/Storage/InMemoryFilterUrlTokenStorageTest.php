<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\FilterUrlToken\Storage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;
use Polysource\Filter\FilterUrlToken\Storage\InMemoryFilterUrlTokenStorage;

#[CoversClass(InMemoryFilterUrlTokenStorage::class)]
final class InMemoryFilterUrlTokenStorageTest extends TestCase
{
    #[Test]
    public function saveThenFindRoundTripsTheToken(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $token = new FilterUrlToken(
            token: 'abc123abcdef',
            resourceName: 'App\\Entity\\Order',
            filtersSlice: ['status' => 'paid'],
            createdAt: new DateTimeImmutable('2026-05-15T10:00:00Z'),
        );

        $storage->save($token);

        self::assertEquals($token, $storage->find('abc123abcdef'));
    }

    #[Test]
    public function findReturnsNullOnUnknownToken(): void
    {
        self::assertNull((new InMemoryFilterUrlTokenStorage())->find('does-not-exist'));
    }

    #[Test]
    public function purgeOlderThanRemovesStaleTokensOnly(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        $old = new FilterUrlToken('aaaa11111111', 'r', ['x' => 1], new DateTimeImmutable('2025-01-01T00:00:00Z'));
        $boundary = new FilterUrlToken('cccc22222222', 'r', ['x' => 1], new DateTimeImmutable('2026-05-01T00:00:00Z'));
        $fresh = new FilterUrlToken('cccc33333333', 'r', ['x' => 1], new DateTimeImmutable('2026-05-15T00:00:00Z'));

        $storage->save($old);
        $storage->save($boundary);
        $storage->save($fresh);

        $removed = $storage->purgeOlderThan(new DateTimeImmutable('2026-05-01T00:00:00Z'));

        // Strict less-than: the boundary token (createdAt == cutoff) survives.
        self::assertSame(1, $removed);
        self::assertNull($storage->find('aaaa11111111'));
        self::assertNotNull($storage->find('cccc22222222'));
        self::assertNotNull($storage->find('cccc33333333'));
    }

    #[Test]
    public function purgeOnEmptyStorageReportsZero(): void
    {
        $storage = new InMemoryFilterUrlTokenStorage();
        self::assertSame(0, $storage->purgeOlderThan(new DateTimeImmutable('now')));
    }
}
