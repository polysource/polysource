<?php

declare(strict_types=1);

namespace Polysource\Widgets\Tests\Unit\Dashboard;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\Widgets\Dashboard\Dashboard;
use Polysource\Widgets\Dashboard\DashboardRegistry;

final class DashboardRegistryTest extends TestCase
{
    public function testGetByName(): void
    {
        $a = new Dashboard('a', 'A', []);
        $b = new Dashboard('b', 'B', []);

        $registry = new DashboardRegistry([$a, $b]);

        self::assertSame($a, $registry->get('a'));
        self::assertSame($b, $registry->get('b'));
        self::assertNull($registry->get('nope'));
    }

    public function testHasByName(): void
    {
        $registry = new DashboardRegistry([new Dashboard('overview', 'O', [])]);
        self::assertTrue($registry->has('overview'));
        self::assertFalse($registry->has('nope'));
    }

    public function testAllReturnsAllRegistered(): void
    {
        $a = new Dashboard('a', 'A', []);
        $b = new Dashboard('b', 'B', []);
        $registry = new DashboardRegistry([$a, $b]);

        self::assertSame([$a, $b], $registry->all());
    }

    public function testDuplicateNameRejected(): void
    {
        $a = new Dashboard('overview', 'A', []);
        $b = new Dashboard('overview', 'B', []);

        $this->expectException(InvalidArgumentException::class);
        new DashboardRegistry([$a, $b]);
    }

    public function testEmptyRegistryIsAccepted(): void
    {
        $registry = new DashboardRegistry([]);
        self::assertSame([], $registry->all());
    }
}
