<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\QuickFilterRowExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

#[CoversClass(QuickFilterRowExtension::class)]
final class QuickFilterRowExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheQuickFilterRowFunction(): void
    {
        $extension = new QuickFilterRowExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_quick_filter_row', $names);
        self::assertCount(1, $names);
    }

    #[Test]
    public function renderEmitsFormWithCurrentPathAndInput(): void
    {
        $extension = new QuickFilterRowExtension($this->makeStack('/admin/orders'));

        $html = (string) $extension->render('status');

        self::assertStringContainsString('<form method="GET" action="/admin/orders"', $html);
        self::assertStringContainsString('name="filters[status]"', $html);
        self::assertStringContainsString('placeholder="filter…"', $html);
    }

    #[Test]
    public function renderPreservesOtherFiltersAsHiddenInputs(): void
    {
        $extension = new QuickFilterRowExtension(
            $this->makeStack('/admin/orders', [
                'filters' => ['country' => 'FR'],
                'sort' => ['createdAt' => 'desc'],
            ]),
        );

        $html = (string) $extension->render('status');

        // Other filter slice preserved as hidden input
        self::assertStringContainsString(
            '<input type="hidden" name="filters[country]" value="FR">',
            $html,
        );
        // Sort preserved as nested hidden input
        self::assertStringContainsString(
            '<input type="hidden" name="sort[createdAt]" value="desc">',
            $html,
        );
        // The current property's filter is NOT included as hidden (the
        // visible input takes ownership; otherwise it would submit twice).
        self::assertStringNotContainsString(
            '<input type="hidden" name="filters[status]"',
            $html,
        );
    }

    #[Test]
    public function renderPrePopulatesInputWithCurrentScalarValue(): void
    {
        $extension = new QuickFilterRowExtension(
            $this->makeStack('/admin/orders', [
                'filters' => ['status' => 'paid'],
            ]),
        );

        $html = (string) $extension->render('status');

        self::assertStringContainsString('value="paid"', $html);
    }

    #[Test]
    public function renderPrePopulatesInputWithCurrentExpandedValue(): void
    {
        $extension = new QuickFilterRowExtension(
            $this->makeStack('/admin/orders', [
                'filters' => ['status' => ['value' => 'paid', 'comparison' => '=']],
            ]),
        );

        $html = (string) $extension->render('status');

        self::assertStringContainsString('value="paid"', $html);
    }

    #[Test]
    public function renderAcceptsCustomPlaceholder(): void
    {
        $extension = new QuickFilterRowExtension($this->makeStack('/admin/orders'));

        $html = (string) $extension->render('reference', 'e.g. ORD-2026-001');

        self::assertStringContainsString('placeholder="e.g. ORD-2026-001"', $html);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function makeStack(string $path, array $query = []): RequestStack
    {
        $stack = new RequestStack();
        $request = Request::create($path, 'GET');
        foreach ($query as $key => $value) {
            /** @var array<int|string, mixed>|string $coerced */
            $coerced = $value;
            $request->query->set($key, $coerced);
        }
        $stack->push($request);

        return $stack;
    }
}
