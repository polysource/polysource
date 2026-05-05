<?php

declare(strict_types=1);

namespace Polysource\Widgets\Tests\Unit\Widget;

use PHPUnit\Framework\TestCase;
use Polysource\Widgets\Widget\ListWidget;

final class ListWidgetTest extends TestCase
{
    public function testRowsAreBuiltFromCallbacks(): void
    {
        $items = [
            ['id' => '1', 'subject' => 'Order #1'],
            ['id' => '2', 'subject' => 'Order #2'],
        ];

        $w = new ListWidget(
            id: 'recent-orders',
            title: 'Recent orders',
            items: $items,
            labelFn: static function (mixed $i): string {
                \assert(\is_array($i));
                $subject = $i['subject'] ?? '';
                \assert(\is_string($subject));

                return $subject;
            },
            hrefFn: static function (mixed $i): string {
                \assert(\is_array($i));
                $id = $i['id'] ?? '';
                \assert(\is_string($id));

                return '/admin/orders/' . $id;
            },
        );

        self::assertSame(
            [
                ['label' => 'Order #1', 'href' => '/admin/orders/1'],
                ['label' => 'Order #2', 'href' => '/admin/orders/2'],
            ],
            $w->getViewData()['rows'],
        );
    }

    public function testHrefIsNullableWhenNoHrefFnProvided(): void
    {
        $w = new ListWidget(
            id: 'l',
            title: 'List',
            items: ['hello'],
            labelFn: static function (mixed $s): string {
                \assert(\is_string($s));

                return $s;
            },
        );

        self::assertSame([['label' => 'hello', 'href' => null]], $w->getViewData()['rows']);
    }

    public function testEmptyItemsProduceEmptyRowList(): void
    {
        $w = new ListWidget('empty', 'Empty', [], static fn (mixed $x): string => \is_scalar($x) ? (string) $x : '');
        self::assertSame(['rows' => []], $w->getViewData());
    }
}
