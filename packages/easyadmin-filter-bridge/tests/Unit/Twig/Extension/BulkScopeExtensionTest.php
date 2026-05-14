<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\BulkScopeExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

#[CoversClass(BulkScopeExtension::class)]
final class BulkScopeExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheThreeBulkScopeFunctions(): void
    {
        $extension = new BulkScopeExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_bulk_scope_toggle', $names);
        self::assertContains('polysource_bulk_dry_run_url', $names);
        self::assertContains('polysource_bulk_scope_active', $names);
        self::assertCount(3, $names);
    }

    #[Test]
    public function scopeToggleRendersCheckboxWithExpectedInputName(): void
    {
        $extension = new BulkScopeExtension();

        $html = (string) $extension->renderScopeToggle('Apply to all 1 234 rows');

        self::assertStringContainsString('type="checkbox"', $html);
        self::assertStringContainsString('name="bulk_scope"', $html);
        self::assertStringContainsString('value="all_matching"', $html);
        self::assertStringContainsString('Apply to all 1 234 rows', $html);
    }

    #[Test]
    public function scopeToggleIsCheckedWhenRequestCarriesTheActiveScope(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/admin/orders/bulk', 'POST', ['bulk_scope' => 'all_matching']);
        $stack->push($request);
        $extension = new BulkScopeExtension($stack);

        $html = (string) $extension->renderScopeToggle();

        self::assertStringContainsString(' checked', $html);
    }

    #[Test]
    public function scopeActiveReturnsTrueOnAllMatchingFlag(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/admin/orders/bulk', 'POST', ['bulk_scope' => 'all_matching']);
        $stack->push($request);
        $extension = new BulkScopeExtension($stack);

        self::assertTrue($extension->scopeActive());
    }

    #[Test]
    public function scopeActiveReturnsFalseWithoutTheFlag(): void
    {
        $stack = new RequestStack();
        $request = Request::create('/admin/orders/bulk', 'POST');
        $stack->push($request);
        $extension = new BulkScopeExtension($stack);

        self::assertFalse($extension->scopeActive());
    }

    #[Test]
    public function dryRunUrlAppendsTheCorrectSeparator(): void
    {
        $extension = new BulkScopeExtension();

        self::assertSame(
            '/admin/orders/bulk?dry_run=1',
            $extension->dryRunUrl('/admin/orders/bulk'),
        );
        self::assertSame(
            '/admin/orders/bulk?action=delete&dry_run=1',
            $extension->dryRunUrl('/admin/orders/bulk?action=delete'),
        );
    }
}
