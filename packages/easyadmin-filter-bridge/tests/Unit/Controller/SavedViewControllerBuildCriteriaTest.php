<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;

/**
 * Pins {@see SavedViewController::buildCriteria()} — the most
 * regression-prone surface of the saved-views feature. The
 * controller decodes URL filter slices into the canonical
 * Polysource FilterCriterion list that is later persisted into
 * the SavedView record.
 *
 * Two URL shapes feed in:
 *
 *   - EasyAdmin's:
 *       filters[X][comparison]=<op>
 *       filters[X][value]=<scalar>|<list>|{min,max}|{from,to}
 *
 *   - Polysource standalone's:
 *       filter[X][op]=<op>
 *       filter[X][value]=<scalar>
 *       filter[X][values][]=<v1>&filter[X][values][]=<v2>
 *       filter[X][min]=<v>&filter[X][max]=<v>          (between)
 *
 *   - Plus EA's BooleanFilter scalar shape:
 *       filters[X]=<scalar>
 *
 * 8 latent regressions surfaced in 2026-05-07 production
 * integration; this test pins every one of them.
 */
final class SavedViewControllerBuildCriteriaTest extends TestCase
{
    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: list<array{property: string, operator: string, values: list<string>}>}>
     */
    public static function provideUrlShapes(): iterable
    {
        // Empty input → no criteria.
        yield 'empty' => [
            [],
            [],
        ];

        // EA TextFilter / NumericFilter with comparison `like`.
        yield 'EA shape: text like' => [
            ['name' => ['comparison' => 'like', 'value' => 'foo']],
            [['property' => 'name', 'operator' => 'like', 'values' => ['foo']]],
        ];

        // EA TextFilter with comparison `=`.
        yield 'EA shape: text equal' => [
            ['name' => ['comparison' => '=', 'value' => 'foo']],
            [['property' => 'name', 'operator' => 'eq', 'values' => ['foo']]],
        ];

        // EA NumericFilter with comparison `>=`.
        yield 'EA shape: numeric gte' => [
            ['stock' => ['comparison' => '>=', 'value' => '100']],
            [['property' => 'stock', 'operator' => 'gte', 'values' => ['100']]],
        ];

        // EA BooleanFilter (scalar shape, no envelope).
        yield 'EA shape: boolean scalar' => [
            ['isSent' => '1'],
            [['property' => 'isSent', 'operator' => 'eq', 'values' => ['1']]],
        ];

        // EA ChoiceFilter with canSelectMultiple → indexed list with comparison `=`.
        // Critical: must map to `in`, not `eq`. Bug found 2026-05-07.
        yield 'EA shape: choice multi → in' => [
            ['status' => ['comparison' => '=', 'value' => ['active', 'draft']]],
            [['property' => 'status', 'operator' => 'in', 'values' => ['active', 'draft']]],
        ];

        // EA between range with min/max.
        yield 'EA shape: between min/max' => [
            ['createdAt' => ['comparison' => 'between', 'value' => ['min' => '2026-01-01', 'max' => '2026-12-31']]],
            [['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']]],
        ];

        // EA between range with from/to (alternative shape).
        yield 'EA shape: between from/to' => [
            ['createdAt' => ['comparison' => 'between', 'value' => ['from' => '2026-01-01', 'to' => '2026-12-31']]],
            [['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']]],
        ];

        // Polysource shape: op + value (scalar).
        yield 'Polysource shape: text like' => [
            ['name' => ['op' => 'like', 'value' => 'foo']],
            [['property' => 'name', 'operator' => 'like', 'values' => ['foo']]],
        ];

        // Polysource shape: op + values (list) → in.
        yield 'Polysource shape: choice multi values' => [
            ['status' => ['op' => 'in', 'values' => ['active', 'draft']]],
            [['property' => 'status', 'operator' => 'in', 'values' => ['active', 'draft']]],
        ];

        // Polysource shape: op + min/max → between.
        yield 'Polysource shape: between' => [
            ['createdAt' => ['op' => 'between', 'min' => '2026-01-01', 'max' => '2026-12-31']],
            [['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']]],
        ];

        // Polysource gt/gte/lt/lte numerics pass through verbatim.
        yield 'Polysource shape: numeric gte' => [
            ['stock' => ['op' => 'gte', 'value' => '100']],
            [['property' => 'stock', 'operator' => 'gte', 'values' => ['100']]],
        ];

        // Empty value → criterion dropped (no leak).
        yield 'empty value scalar' => [
            ['name' => ['comparison' => 'like', 'value' => '']],
            [],
        ];

        // Empty values list → criterion dropped.
        yield 'empty values list' => [
            ['status' => ['comparison' => '=', 'value' => []]],
            [],
        ];

        // Both min and max empty → criterion dropped.
        yield 'between with empty bounds' => [
            ['createdAt' => ['comparison' => 'between', 'value' => ['min' => '', 'max' => '']]],
            [],
        ];

        // v0.9.0 hardening: unknown operator strings no longer
        // pass through verbatim — they fall back to the default
        // (`eq` here). Previous behaviour returned the raw operator
        // unchanged, which let a hostile client persist a criterion
        // with arbitrary operator text downstream consumers had to
        // defensively reject. Caught by architectural audit.
        yield 'v0.9.0: unknown operator falls back to default eq' => [
            ['name' => ['comparison' => 'UNION', 'value' => 'foo']],
            [['property' => 'name', 'operator' => 'eq', 'values' => ['foo']]],
        ];

        // Polysource canonical operator names round-trip unchanged
        // (`eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `between` are now
        // explicitly recognised, not falling through default).
        yield 'v0.9.0: canonical neq round-trips' => [
            ['name' => ['comparison' => 'neq', 'value' => 'foo']],
            [['property' => 'name', 'operator' => 'neq', 'values' => ['foo']]],
        ];

        // Mix of multiple criteria in one URL.
        yield 'mixed multi' => [
            [
                'name' => ['comparison' => 'like', 'value' => 'foo'],
                'isSent' => '1',
                'status' => ['comparison' => '=', 'value' => ['active']],
            ],
            [
                ['property' => 'name', 'operator' => 'like', 'values' => ['foo']],
                ['property' => 'isSent', 'operator' => 'eq', 'values' => ['1']],
                ['property' => 'status', 'operator' => 'in', 'values' => ['active']],
            ],
        ];
    }

    /**
     * @param array<string, mixed>                                                      $raw
     * @param list<array{property: string, operator: string, values: list<string>}>     $expected
     */
    #[Test]
    #[DataProvider('provideUrlShapes')]
    public function buildCriteriaProducesCanonicalShape(array $raw, array $expected): void
    {
        $criteria = SavedViewController::buildCriteria($raw);

        self::assertCount(\count($expected), $criteria, 'Criterion count must match');

        foreach ($expected as $index => $exp) {
            self::assertSame($exp['property'], $criteria[$index]->property, "Criterion #$index property mismatch");
            self::assertSame($exp['operator'], $criteria[$index]->operator, "Criterion #$index operator mismatch (URL → canonical translation)");
            self::assertSame($exp['values'], $criteria[$index]->values, "Criterion #$index values mismatch");
        }
    }
}
