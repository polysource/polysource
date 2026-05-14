<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowDensityExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

#[CoversClass(RowDensityExtension::class)]
final class RowDensityExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheThreeRowDensityFunctions(): void
    {
        $extension = new RowDensityExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_row_density_class', $names);
        self::assertContains('polysource_row_density_current', $names);
        self::assertContains('polysource_row_density_toggle', $names);
        self::assertCount(3, $names);
    }

    #[Test]
    public function defaultsToNormalDensityWithoutAnyRequest(): void
    {
        $extension = new RowDensityExtension();

        self::assertSame('normal', $extension->currentDensity());
        self::assertSame('', $extension->tableClass());
    }

    #[Test]
    public function readsCompactDensityFromUrlQueryString(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/admin/orders?density=compact'));
        $extension = new RowDensityExtension($stack);

        self::assertSame('compact', $extension->currentDensity());
        self::assertSame('table-sm', $extension->tableClass());
    }

    #[Test]
    public function unknownDensityValueFallsBackToNormal(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/admin/orders?density=tinyhuman'));
        $extension = new RowDensityExtension($stack);

        self::assertSame('normal', $extension->currentDensity());
        self::assertSame('', $extension->tableClass());
    }

    #[Test]
    public function toggleRendersTwoAnchorsWithBootstrapClasses(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/admin/orders'));
        $extension = new RowDensityExtension($stack);

        $html = (string) $extension->renderToggle();

        self::assertStringContainsString('<div class="btn-group btn-group-sm polysource-row-density-toggle"', $html);
        self::assertStringContainsString('Normal</a>', $html);
        self::assertStringContainsString('Compact</a>', $html);
        self::assertStringContainsString('href="/admin/orders?density=normal"', $html);
        self::assertStringContainsString('href="/admin/orders?density=compact"', $html);
    }

    #[Test]
    public function activeAnchorReflectsCurrentDensity(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/admin/orders?density=compact'));
        $extension = new RowDensityExtension($stack);

        $html = (string) $extension->renderToggle();

        // The Compact anchor wears the filled `btn btn-secondary active`
        // class; the Normal anchor stays outline.
        self::assertMatchesRegularExpression(
            '#href="/admin/orders\\?density=compact" class="btn btn-secondary active" aria-pressed="true"#',
            $html,
        );
        self::assertMatchesRegularExpression(
            '#href="/admin/orders\\?density=normal" class="btn btn-outline-secondary" aria-pressed="false"#',
            $html,
        );
    }

    #[Test]
    public function togglePreservesOtherQueryParameters(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/admin/orders?filters%5Bstatus%5D=paid&sort=name&density=normal'));
        $extension = new RowDensityExtension($stack);

        $html = (string) $extension->renderToggle();

        // Other query slices come back wrapped into the toggle anchors,
        // so clicking density never drops the user's filters/sort.
        self::assertStringContainsString('filters%5Bstatus%5D=paid', $html);
        self::assertStringContainsString('sort=name', $html);
    }
}
