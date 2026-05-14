<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FrozenColumnExtension;
use Twig\TwigFunction;

#[CoversClass(FrozenColumnExtension::class)]
final class FrozenColumnExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheFrozenColumnFunction(): void
    {
        $extension = new FrozenColumnExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertSame(['polysource_frozen_column'], $names);
    }

    #[Test]
    public function defaultArgumentsPinToLeftEdgeWithZeroOffset(): void
    {
        $html = (string) (new FrozenColumnExtension())->attrs();

        self::assertStringContainsString('class="polysource-frozen-column polysource-frozen-column--left"', $html);
        self::assertStringContainsString('position: sticky', $html);
        self::assertStringContainsString('left: 0px', $html);
        self::assertStringContainsString('z-index: 2', $html);
        self::assertStringContainsString('background-color: var(--bs-body-bg, #fff)', $html);
    }

    #[Test]
    public function offsetIsPropagatedToTheStyleAttribute(): void
    {
        $html = (string) (new FrozenColumnExtension())->attrs('left', 60);

        self::assertStringContainsString('left: 60px', $html);
        self::assertStringContainsString('polysource-frozen-column--left', $html);
    }

    #[Test]
    public function rightSidePinsToRightEdge(): void
    {
        $html = (string) (new FrozenColumnExtension())->attrs('right');

        self::assertStringContainsString('class="polysource-frozen-column polysource-frozen-column--right"', $html);
        self::assertStringContainsString('right: 0px', $html);
        self::assertStringNotContainsString('left:', $html);
    }

    #[Test]
    public function unknownSideValuesFallBackToLeft(): void
    {
        // Defensive: an unrecognised side string should not break
        // rendering — the helper normalises to "left" so the table
        // keeps working even if the host typo'd the argument.
        $html = (string) (new FrozenColumnExtension())->attrs('top');

        self::assertStringContainsString('left:', $html);
        self::assertStringContainsString('polysource-frozen-column--left', $html);
    }

    #[Test]
    public function negativeOffsetIsClampedToZero(): void
    {
        $html = (string) (new FrozenColumnExtension())->attrs('left', -42);

        self::assertStringContainsString('left: 0px', $html);
    }
}
