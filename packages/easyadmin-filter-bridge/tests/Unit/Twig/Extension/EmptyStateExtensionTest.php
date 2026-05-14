<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\EmptyStateExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\TwigFunction;

#[CoversClass(EmptyStateExtension::class)]
final class EmptyStateExtensionTest extends TestCase
{
    #[Test]
    public function exposesTheThreeExpectedTwigFunctions(): void
    {
        $extension = new EmptyStateExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_has_active_filters', $names);
        self::assertContains('polysource_clear_filters_url', $names);
        self::assertContains('polysource_active_filters_summary', $names);
        self::assertCount(3, $names);
    }

    #[Test]
    public function hasActiveFiltersReturnsTrueWhenUrlCarriesAFilterSlice(): void
    {
        $stack = $this->makeStack('/admin/orders', ['filters' => ['status' => 'paid']]);
        $extension = new EmptyStateExtension($stack);

        self::assertTrue($extension->hasActiveFilters());
    }

    #[Test]
    public function hasActiveFiltersReturnsFalseOnCleanUrl(): void
    {
        $stack = $this->makeStack('/admin/orders');
        $extension = new EmptyStateExtension($stack);

        self::assertFalse($extension->hasActiveFilters());
    }

    #[Test]
    public function clearFiltersUrlStripsFiltersKeysAndPreservesOthers(): void
    {
        $stack = $this->makeStack('/admin/orders', [
            'filters' => ['status' => 'paid'],
            'sort' => ['createdAt' => 'desc'],
            'page' => '2',
        ]);
        $extension = new EmptyStateExtension($stack);

        $url = $extension->clearFiltersUrl();

        self::assertStringNotContainsString('filters', $url);
        self::assertStringContainsString('sort%5BcreatedAt%5D=desc', $url);
        self::assertStringContainsString('page=2', $url);
    }

    #[Test]
    public function clearFiltersUrlReturnsBarePathWhenNoOtherQueryRemains(): void
    {
        $stack = $this->makeStack('/admin/orders', ['filters' => ['status' => 'paid']]);
        $extension = new EmptyStateExtension($stack);

        self::assertSame('/admin/orders', $extension->clearFiltersUrl());
    }

    #[Test]
    public function activeFiltersSummaryReturnsScalarSlices(): void
    {
        $stack = $this->makeStack('/admin/orders', [
            'filters' => [
                'status' => 'paid',
                'country' => 'FR',
            ],
        ]);
        $extension = new EmptyStateExtension($stack);

        $summary = $extension->activeFiltersSummary();

        self::assertCount(2, $summary);
        self::assertSame(['property' => 'status', 'value' => 'paid'], $summary[0]);
        self::assertSame(['property' => 'country', 'value' => 'FR'], $summary[1]);
    }

    #[Test]
    public function activeFiltersSummaryExtractsValueFromExpandedShape(): void
    {
        $stack = $this->makeStack('/admin/orders', [
            'filters' => [
                'status' => ['value' => 'paid', 'comparison' => '!='],
            ],
        ]);
        $extension = new EmptyStateExtension($stack);

        $summary = $extension->activeFiltersSummary();

        self::assertSame([['property' => 'status', 'value' => 'paid']], $summary);
    }

    #[Test]
    public function activeFiltersSummaryJoinsListValues(): void
    {
        $stack = $this->makeStack('/admin/orders', [
            'filters' => [
                'country' => ['FR', 'DE', 'IT'],
            ],
        ]);
        $extension = new EmptyStateExtension($stack);

        $summary = $extension->activeFiltersSummary();

        self::assertSame([['property' => 'country', 'value' => 'FR, DE, IT']], $summary);
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
