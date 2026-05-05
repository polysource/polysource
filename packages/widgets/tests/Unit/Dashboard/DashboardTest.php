<?php

declare(strict_types=1);

namespace Polysource\Widgets\Tests\Unit\Dashboard;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\Widgets\Dashboard\Dashboard;
use Polysource\Widgets\Widget\CounterWidget;

final class DashboardTest extends TestCase
{
    public function testHappyPathBuildsImmutableDashboard(): void
    {
        $row = [
            new CounterWidget('a', 'A', 1),
            new CounterWidget('b', 'B', 2),
        ];

        $dashboard = new Dashboard('overview', 'Overview', [$row]);

        self::assertSame('overview', $dashboard->name);
        self::assertSame('Overview', $dashboard->title);
        self::assertCount(1, $dashboard->rows);
        self::assertCount(2, $dashboard->rows[0]);
    }

    public function testEmptyNameRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Dashboard('', 'X', []);
    }

    public function testEmptyTitleRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Dashboard('x', '', []);
    }

    public function testNonWidgetCellRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // @phpstan-ignore-next-line argument.type — exercising runtime guard
        new Dashboard('x', 'X', [['not-a-widget']]);
    }

    public function testEmptyRowsAreAccepted(): void
    {
        // A dashboard can be empty (e.g. all widgets gated by perms).
        $dashboard = new Dashboard('empty', 'Empty', []);
        self::assertSame([], $dashboard->rows);
    }
}
