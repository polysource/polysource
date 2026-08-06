<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FilterTreeExtension;
use Polysource\EasyAdminFilterBridge\Twig\FilterTreeBuilder;
use Twig\TwigFunction;

/**
 * The tree-building logic itself is covered by FilterTreeBuilderTest;
 * this locks the Twig-facing contract: the function name the
 * `crud/filters.html.twig` override calls, and the empty-tree shape
 * it iterates when a CRUD has no filter config.
 */
#[CoversClass(FilterTreeExtension::class)]
final class FilterTreeExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheFilterTreeFunction(): void
    {
        $extension = new FilterTreeExtension(new FilterTreeBuilder());

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertSame(['polysource_filter_tree'], $names);
    }

    #[Test]
    public function nullFilterConfigYieldsTheEmptyTreeShape(): void
    {
        $tree = (new FilterTreeExtension(new FilterTreeBuilder()))->buildTree(null);

        self::assertSame(['ungrouped' => [], 'groups' => [], 'tabs' => []], $tree);
    }
}
