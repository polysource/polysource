<?php

declare(strict_types=1);

namespace Polysource\Widgets\Tests\Unit\Widget;

use ArrayObject;
use Generator;
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

    public function testItemsCanBeProvidedAsAPerRequestClosure(): void
    {
        // v0.6.1 — closures are invoked at getViewData() time so the
        // widget can carry user-scoped data without freezing it at
        // boot-time DI compilation. Closes Tier 3 item 11.
        $state = new ArrayObject(['calls' => 0]);
        $w = new ListWidget(
            id: 'recent-orders',
            title: 'Recently viewed',
            items: static function () use ($state): iterable {
                $calls = $state['calls'] ?? 0;
                \assert(\is_int($calls));
                $state['calls'] = $calls + 1;

                return [
                    ['id' => 'a', 'label' => 'Alpha'],
                    ['id' => 'b', 'label' => 'Beta'],
                ];
            },
            labelFn: static function (mixed $i): string {
                \assert(\is_array($i));

                return \is_string($i['label']) ? $i['label'] : '';
            },
        );

        // Closure not invoked until rendering.
        self::assertSame(0, $state['calls']);

        $rows1 = $w->getViewData()['rows'];
        self::assertSame(1, $state['calls'], 'closure must be invoked once per render');
        self::assertSame(
            [['label' => 'Alpha', 'href' => null], ['label' => 'Beta', 'href' => null]],
            $rows1,
        );

        // A second render re-invokes the closure (per-request semantics).
        $w->getViewData();
        self::assertSame(2, $state['calls'], 'closure must be re-invoked on each getViewData() call');
    }

    public function testClosureCanReturnAGeneratorForMemoryEfficiency(): void
    {
        $w = new ListWidget(
            id: 'lazy',
            title: 'Lazy',
            items: static function (): Generator {
                yield 'x';
                yield 'y';
            },
            labelFn: static fn (mixed $s): string => \is_string($s) ? $s : '',
        );

        self::assertSame(
            [['label' => 'x', 'href' => null], ['label' => 'y', 'href' => null]],
            $w->getViewData()['rows'],
        );
    }
}
