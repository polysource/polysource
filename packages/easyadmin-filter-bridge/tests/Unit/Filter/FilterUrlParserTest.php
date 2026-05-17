<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Filter\FilterUrlParser;

/**
 * Pins {@see FilterUrlParser::buildCriteria()} — the decomposed
 * successor of `SavedViewController::buildCriteria` (v0.9.0). The
 * controller test still covers the full data-driven matrix via the
 * thin static shim; this class adds focused tests for the new
 * pipeline's small helpers (extractComparison, promoteFromValues,
 * promoteToBetween, buildBetween, buildIn) reached indirectly
 * through buildCriteria.
 */
#[CoversClass(FilterUrlParser::class)]
final class FilterUrlParserTest extends TestCase
{
    #[Test]
    public function buildCriteriaEmptyInputReturnsEmptyList(): void
    {
        self::assertSame([], FilterUrlParser::buildCriteria([]));
    }

    #[Test]
    public function hardenedDefaultPreventsUnknownOperatorPassthrough(): void
    {
        // v0.9.0 hardening: hostile / unknown operator strings no
        // longer pass through verbatim. Caught by the audit; pinned
        // here so a regression that re-introduces passthrough flips
        // this red.
        $result = FilterUrlParser::buildCriteria([
            'name' => ['comparison' => 'UNION', 'value' => 'foo'],
        ]);

        self::assertCount(1, $result);
        self::assertSame('eq', $result[0]->operator);
    }

    #[Test]
    public function eaScalarShapeAccepted(): void
    {
        $result = FilterUrlParser::buildCriteria(['isSent' => '1']);

        self::assertCount(1, $result);
        self::assertSame('isSent', $result[0]->property);
        self::assertSame('eq', $result[0]->operator);
        self::assertSame(['1'], $result[0]->values);
    }

    #[Test]
    public function emptyScalarShapeDropped(): void
    {
        self::assertSame([], FilterUrlParser::buildCriteria(['name' => '']));
        self::assertSame([], FilterUrlParser::buildCriteria(['name' => null]));
    }

    #[Test]
    public function polysourceValuesListPromotedToIn(): void
    {
        // Polysource shape: ?filter[X][values][]=a&[values][]=b
        $result = FilterUrlParser::buildCriteria([
            'status' => ['op' => 'in', 'values' => ['active', 'draft']],
        ]);

        self::assertCount(1, $result);
        self::assertSame('in', $result[0]->operator);
        self::assertSame(['active', 'draft'], $result[0]->values);
    }

    #[Test]
    public function polysourceMinMaxPromotedToBetweenEnvelope(): void
    {
        $result = FilterUrlParser::buildCriteria([
            'createdAt' => ['op' => 'between', 'min' => '2026-01-01', 'max' => '2026-12-31'],
        ]);

        self::assertCount(1, $result);
        self::assertSame('between', $result[0]->operator);
        self::assertSame(['2026-01-01', '2026-12-31'], $result[0]->values);
    }

    #[Test]
    public function eaBetweenFromToShapeAccepted(): void
    {
        $result = FilterUrlParser::buildCriteria([
            'createdAt' => ['comparison' => 'between', 'value' => ['from' => '2026-01-01', 'to' => '2026-12-31']],
        ]);

        self::assertSame(['2026-01-01', '2026-12-31'], $result[0]->values);
    }

    #[Test]
    public function emptyBetweenBoundsDropped(): void
    {
        self::assertSame([], FilterUrlParser::buildCriteria([
            'createdAt' => ['comparison' => 'between', 'value' => ['min' => '', 'max' => '']],
        ]));
    }

    #[Test]
    public function indexedListAlwaysCollapsesToIn(): void
    {
        // Even if EA's ChoiceFilter submits `comparison==`, an indexed
        // list value collapses to `in`. Pre-v0.9.0 bug: a
        // single-select submission as `value[]` was stored as `eq`
        // against an array, which data sources couldn't honour.
        $result = FilterUrlParser::buildCriteria([
            'status' => ['comparison' => '=', 'value' => ['active']],
        ]);

        self::assertSame('in', $result[0]->operator);
        self::assertSame(['active'], $result[0]->values);
    }

    #[Test]
    public function multipleCriteriaIndependentlyParsed(): void
    {
        $result = FilterUrlParser::buildCriteria([
            'name' => ['comparison' => 'like', 'value' => 'foo'],
            'isSent' => '1',
            'status' => ['comparison' => '=', 'value' => ['active', 'paused']],
        ]);

        self::assertCount(3, $result);
        self::assertSame('like', $result[0]->operator);
        self::assertSame('eq', $result[1]->operator);
        self::assertSame('in', $result[2]->operator);
    }
}
