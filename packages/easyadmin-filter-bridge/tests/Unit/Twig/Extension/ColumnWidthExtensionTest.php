<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ColumnWidthExtension;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Twig\TwigFunction;

#[CoversClass(ColumnWidthExtension::class)]
final class ColumnWidthExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheTwoColumnWidthFunctions(): void
    {
        $extension = new ColumnWidthExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_column_width_style', $names);
        self::assertContains('polysource_column_width', $names);
        self::assertCount(2, $names);
    }

    #[Test]
    public function returnsNullWidthForANullView(): void
    {
        $extension = new ColumnWidthExtension();

        self::assertNull($extension->widthFor(null, 'reference'));
        self::assertSame('', (string) $extension->styleFor(null, 'reference'));
    }

    #[Test]
    public function returnsNullWidthForAnUnknownColumn(): void
    {
        $view = $this->buildView(columnWidths: ['reference' => 200]);
        $extension = new ColumnWidthExtension();

        self::assertNull($extension->widthFor($view, 'status'));
        self::assertSame('', (string) $extension->styleFor($view, 'status'));
    }

    #[Test]
    public function returnsThePixelWidthStoredOnTheView(): void
    {
        $view = $this->buildView(columnWidths: ['reference' => 240, 'status' => 80]);
        $extension = new ColumnWidthExtension();

        self::assertSame(240, $extension->widthFor($view, 'reference'));
        self::assertSame(80, $extension->widthFor($view, 'status'));
    }

    #[Test]
    public function emitsInlineStyleAttributeWhenWidthIsSet(): void
    {
        $view = $this->buildView(columnWidths: ['reference' => 240]);
        $extension = new ColumnWidthExtension();

        self::assertSame('style="width: 240px"', (string) $extension->styleFor($view, 'reference'));
    }

    /**
     * @param array<string, int> $columnWidths
     */
    private function buildView(array $columnWidths): SavedView
    {
        return new SavedView(
            id: 'view-1',
            name: 'My view',
            resourceName: 'orders',
            ownerId: 'user-1',
            scope: SavedViewScope::PRIVATE,
            filters: new FilterCollection('orders'),
            columns: ['reference', 'status'],
            columnWidths: $columnWidths,
        );
    }
}
