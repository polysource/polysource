<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Url\FilterUrlBuilder;
use Polysource\Filter\Url\OperatorMap;

#[CoversClass(FilterUrlBuilder::class)]
final class FilterUrlBuilderTest extends TestCase
{
    #[Test]
    public function mergeAddsFilterSliceToEmptyQuery(): void
    {
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: [],
            property: 'status',
            eaOperator: '=',
            value: 'paid',
        );

        self::assertSame([
            'filters' => [
                'status' => ['comparison' => '=', 'value' => 'paid'],
            ],
        ], $merged);
    }

    #[Test]
    public function mergePreservesUnrelatedQueryKeys(): void
    {
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: ['sort' => ['createdAt' => 'desc'], 'page' => '2'],
            property: 'status',
            eaOperator: '=',
            value: 'paid',
        );

        self::assertSame([
            'sort' => ['createdAt' => 'desc'],
            'page' => '2',
            'filters' => [
                'status' => ['comparison' => '=', 'value' => 'paid'],
            ],
        ], $merged);
    }

    #[Test]
    public function mergePreservesUnrelatedFilters(): void
    {
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: ['filters' => ['country' => 'FR']],
            property: 'status',
            eaOperator: '=',
            value: 'paid',
        );

        self::assertSame([
            'filters' => [
                'country' => 'FR',
                'status' => ['comparison' => '=', 'value' => 'paid'],
            ],
        ], $merged);
    }

    #[Test]
    public function mergeOverwritesSamePropertyFilter(): void
    {
        // "Refine" UX: a previous criterion on `status` is replaced
        // by the new one rather than appended (EA only supports one
        // value per property).
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: ['filters' => ['status' => ['comparison' => '=', 'value' => 'paid']]],
            property: 'status',
            eaOperator: '!=',
            value: 'cancelled',
        );

        self::assertSame([
            'filters' => [
                'status' => ['comparison' => '!=', 'value' => 'cancelled'],
            ],
        ], $merged);
    }

    #[Test]
    public function mergeWithReplaceDropsExistingFilters(): void
    {
        // "Show only this X" UX: drop every existing filter and emit
        // the new criterion as the only one.
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: [
                'filters' => ['country' => 'FR'],
                'sort' => ['createdAt' => 'desc'],
            ],
            property: 'status',
            eaOperator: '=',
            value: 'paid',
            replace: true,
        );

        self::assertSame([
            'filters' => [
                'status' => ['comparison' => '=', 'value' => 'paid'],
            ],
        ], $merged);
    }

    #[Test]
    public function mergeAlwaysEmitsExpandedShape(): void
    {
        // Regression-pin for dogfood signal #10 (v0.8.1): the scalar
        // shorthand `filters[X]=v` is silently dropped by EA's filter
        // pipeline. The builder MUST always emit the expanded EA
        // shape (`comparison` + `value` envelope) so chips actually
        // narrow rows.
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: [],
            property: 'status',
            eaOperator: '=',
            value: 'paid',
        );

        self::assertIsArray($merged['filters']['status']);
        self::assertSame('=', $merged['filters']['status']['comparison']);
        self::assertSame('paid', $merged['filters']['status']['value']);
    }

    #[Test]
    public function toPathWithQueryReturnsBarePathWhenQueryEmpty(): void
    {
        self::assertSame('/admin/orders', FilterUrlBuilder::toPathWithQuery('/admin/orders', []));
    }

    #[Test]
    public function toPathWithQueryEncodesSpecialCharacters(): void
    {
        $url = FilterUrlBuilder::toPathWithQuery(
            '/admin/orders',
            ['filters' => ['status' => ['comparison' => '=', 'value' => 'paid & current']]],
        );

        // Ampersand in value must be URL-encoded — otherwise the
        // browser splits the query into two parameters.
        self::assertStringContainsString('paid%20%26%20current', $url);
        self::assertStringStartsWith('/admin/orders?', $url);
    }

    #[Test]
    public function integratesWithOperatorMapForCanonicalInputs(): void
    {
        // Typical callsite: criterion has canonical operator name,
        // builder needs the EA URL form. Pipeline:
        //   criterion.operator → OperatorMap::toEa → builder
        $merged = FilterUrlBuilder::mergeCriterion(
            existingQuery: [],
            property: 'createdAt',
            eaOperator: OperatorMap::toEa(OperatorMap::GTE),
            value: '2026-01-01',
        );

        self::assertSame('>=', $merged['filters']['createdAt']['comparison']);
    }
}
