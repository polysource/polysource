<?php

declare(strict_types=1);

namespace Polysource\Widgets\Tests\Unit\Widget;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\Widgets\Widget\CounterWidget;

final class CounterWidgetTest extends TestCase
{
    public function testHappyPathExposesViewData(): void
    {
        $w = new CounterWidget(
            id: 'mrr',
            title: 'MRR',
            value: 42300,
            unit: '$',
            trend: 'up',
            palette: 'success',
        );

        self::assertSame('mrr', $w->getId());
        self::assertSame('MRR', $w->getTitle());
        self::assertSame(3, $w->getColumnSpan());
        self::assertSame('@PolysourceWidgets/widgets/counter.html.twig', $w->getTemplate());
        self::assertSame(
            ['value' => 42300, 'unit' => '$', 'trend' => 'up', 'palette' => 'success'],
            $w->getViewData(),
        );
    }

    public function testFloatValueIsAccepted(): void
    {
        $w = new CounterWidget('p95', 'P95 latency', 124.5, 'ms');
        self::assertSame(124.5, $w->getViewData()['value']);
    }

    public function testPaletteDefaultsToSecondary(): void
    {
        $w = new CounterWidget('x', 'X', 1);
        self::assertSame('secondary', $w->getViewData()['palette']);
    }

    public function testEmptyIdRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CounterWidget('', 'X', 1);
    }

    public function testInvalidColumnSpanRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CounterWidget('x', 'X', 1, columnSpan: 13);
    }
}
