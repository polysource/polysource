<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Filter\UrlFilterApplier;

/**
 * Behavioural test for {@see UrlFilterApplier}.
 *
 * Covers each supported URL shape and the defensive property
 * validation. QueryBuilder is mocked — we assert the DQL fragments
 * and parameters the applier emits, not the actual SQL execution.
 */
#[CoversClass(UrlFilterApplier::class)]
final class UrlFilterApplierTest extends TestCase
{
    #[Test]
    public function appliesNoClausesWhenQueryHasNoFiltersKey(): void
    {
        $qb = $this->mockQb(expectedAndWheres: []);
        $applier = new UrlFilterApplier();

        $count = $applier->apply($qb, [], $this->stubMetadata(['status']), 'e');

        self::assertSame(0, $count);
    }

    #[Test]
    public function dropsFiltersForPropertiesNotMappedByDoctrine(): void
    {
        // `unknown` not in the metadata → must be silently dropped.
        // No DQL injection risk: the QB never sees that string.
        $qb = $this->mockQb(expectedAndWheres: []);
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['unknown' => 'x']],
            $this->stubMetadata(['status']),
            'e',
        );

        self::assertSame(0, $count);
    }

    #[Test]
    public function appliesScalarEqualityClauseForMappedProperty(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.status = :polysource_f_0'],
            expectedParams: [['polysource_f_0', 'paid']],
        );
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['status' => 'paid']],
            $this->stubMetadata(['status']),
            'e',
        );

        self::assertSame(1, $count);
    }

    #[Test]
    public function dropsScalarEqualityWhenValueIsEmptyString(): void
    {
        $qb = $this->mockQb(expectedAndWheres: []);
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['status' => '']],
            $this->stubMetadata(['status']),
            'e',
        );

        self::assertSame(0, $count);
    }

    #[Test]
    public function coercesBooleanStringsToBoolParameters(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.active = :polysource_f_0'],
            expectedParams: [['polysource_f_0', true]],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['active' => 'true']],
            $this->stubMetadata(['active']),
            'e',
        );
    }

    #[Test]
    public function emitsInClauseForListStyleMultiSelect(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.country IN (:polysource_f_0)'],
            expectedParams: [['polysource_f_0', ['FR', 'DE']]],
        );
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['country' => ['FR', 'DE']]],
            $this->stubMetadata(['country']),
            'e',
        );

        self::assertSame(1, $count);
    }

    #[Test]
    public function dropsListWhenAllValuesAreEmpty(): void
    {
        $qb = $this->mockQb(expectedAndWheres: []);
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['country' => ['', '']]],
            $this->stubMetadata(['country']),
            'e',
        );

        self::assertSame(0, $count);
    }

    #[Test]
    public function appliesExpandedShapeWithEqualityComparison(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.status = :polysource_f_0'],
            expectedParams: [['polysource_f_0', 'paid']],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['status' => ['comparison' => '=', 'value' => 'paid']]],
            $this->stubMetadata(['status']),
            'e',
        );
    }

    #[Test]
    public function appliesExpandedShapeWithInequalityComparison(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.status != :polysource_f_0'],
            expectedParams: [['polysource_f_0', 'archived']],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['status' => ['comparison' => '!=', 'value' => 'archived']]],
            $this->stubMetadata(['status']),
            'e',
        );
    }

    #[Test]
    public function appliesExpandedShapeWithUpperCaseInComparison(): void
    {
        // Friction C10 (v0.5.7) — EA's `InFilter` form submits
        // `filters[X][comparison]=IN` + `filters[X][value][]=v1` shape.
        // The applier's match block previously only knew about scalar
        // comparison operators (`=`, `!=`, `<`, …), so every IN-shape
        // submission fell through to default→null and was silently
        // dropped. Result: every `InFilter` query returned ALL rows.
        $qb = $this->mockQb(
            expectedAndWheres: ['e.status IN (:polysource_f_0)'],
            expectedParams: [['polysource_f_0', ['draft', 'archived']]],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['status' => ['comparison' => 'IN', 'value' => ['draft', 'archived']]]],
            $this->stubMetadata(['status']),
            'e',
        );
    }

    #[Test]
    public function appliesExpandedShapeWithNotInComparison(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.status NOT IN (:polysource_f_0)'],
            expectedParams: [['polysource_f_0', ['archived']]],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['status' => ['comparison' => 'NOT IN', 'value' => ['archived']]]],
            $this->stubMetadata(['status']),
            'e',
        );
    }

    #[Test]
    public function appliesExpandedShapeWithIsNullComparison(): void
    {
        // NotNullFilter (the bridge's tri-state Any/Has value/Empty)
        // submits `comparison=IS NULL` with no value. Same C10 bug
        // dropped these silently.
        $qb = $this->mockQb(
            expectedAndWheres: ['e.deletedAt IS NULL'],
            expectedParams: [],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['deletedAt' => ['comparison' => 'IS NULL']]],
            $this->stubMetadata(['deletedAt']),
            'e',
        );
    }

    #[Test]
    public function appliesExpandedShapeWithIsNotNullComparison(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.deletedAt IS NOT NULL'],
            expectedParams: [],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['deletedAt' => ['comparison' => 'IS NOT NULL']]],
            $this->stubMetadata(['deletedAt']),
            'e',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideOrderingOperators(): iterable
    {
        yield 'lt' => ['<', '<'];
        yield 'lte' => ['<=', '<='];
        yield 'gt' => ['>', '>'];
        yield 'gte' => ['>=', '>='];
    }

    #[Test]
    #[DataProvider('provideOrderingOperators')]
    public function appliesExpandedShapeWithOrderingComparison(string $input, string $expected): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: [\sprintf('e.totalCents %s :polysource_f_0', $expected)],
            expectedParams: [['polysource_f_0', '100']],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['totalCents' => ['comparison' => $input, 'value' => '100']]],
            $this->stubMetadata(['totalCents']),
            'e',
        );
    }

    #[Test]
    public function appliesBetweenShapeWithLowAndHighParameters(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.totalCents BETWEEN :polysource_f_0_low AND :polysource_f_0_high'],
            expectedParams: [
                ['polysource_f_0_low', '100'],
                ['polysource_f_0_high', '500'],
            ],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['totalCents' => [
                'comparison' => 'between',
                'value' => '100',
                'value2' => '500',
            ]]],
            $this->stubMetadata(['totalCents']),
            'e',
        );
    }

    #[Test]
    public function dropsBetweenShapeWhenSecondValueIsMissing(): void
    {
        $qb = $this->mockQb(expectedAndWheres: []);
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['totalCents' => [
                'comparison' => 'between',
                'value' => '100',
            ]]],
            $this->stubMetadata(['totalCents']),
            'e',
        );

        self::assertSame(0, $count);
    }

    #[Test]
    public function appliesLikeComparisonWithWildcardWrap(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.name LIKE :polysource_f_0'],
            expectedParams: [['polysource_f_0', '%alice%']],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['name' => ['comparison' => 'like', 'value' => 'alice']]],
            $this->stubMetadata(['name']),
            'e',
        );
    }

    #[Test]
    public function appliesNotLikeComparison(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: ['e.name NOT LIKE :polysource_f_0'],
            expectedParams: [['polysource_f_0', '%bot%']],
        );
        $applier = new UrlFilterApplier();

        $applier->apply(
            $qb,
            ['filters' => ['name' => ['comparison' => 'not like', 'value' => 'bot']]],
            $this->stubMetadata(['name']),
            'e',
        );
    }

    #[Test]
    public function dropsExpandedShapeWithUnknownComparison(): void
    {
        $qb = $this->mockQb(expectedAndWheres: []);
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['status' => ['comparison' => 'unsafe sql', 'value' => 'paid']]],
            $this->stubMetadata(['status']),
            'e',
        );

        self::assertSame(0, $count);
    }

    #[Test]
    public function appliesMultipleFiltersAndCountsThem(): void
    {
        $qb = $this->mockQb(
            expectedAndWheres: [
                'e.status = :polysource_f_0',
                'e.country IN (:polysource_f_1)',
            ],
            expectedParams: [
                ['polysource_f_0', 'paid'],
                ['polysource_f_1', ['FR', 'DE']],
            ],
        );
        $applier = new UrlFilterApplier();

        $count = $applier->apply(
            $qb,
            ['filters' => ['status' => 'paid', 'country' => ['FR', 'DE']]],
            $this->stubMetadata(['status', 'country']),
            'e',
        );

        self::assertSame(2, $count);
    }

    /**
     * Build a QueryBuilder mock asserting the expected `andWhere` /
     * `setParameter` calls happen in order and no extras leak through.
     *
     * @param list<string>             $expectedAndWheres
     * @param list<array{string, mixed}> $expectedParams
     */
    private function mockQb(array $expectedAndWheres, array $expectedParams = []): QueryBuilder
    {
        $qb = $this->createMock(QueryBuilder::class);

        if ([] === $expectedAndWheres) {
            $qb->expects(self::never())->method('andWhere');
        } else {
            $matcher = self::exactly(\count($expectedAndWheres));
            $qb->expects($matcher)
                ->method('andWhere')
                ->willReturnCallback(static function (mixed $dql) use ($matcher, $expectedAndWheres, $qb): QueryBuilder {
                    $idx = $matcher->numberOfInvocations() - 1;
                    self::assertSame($expectedAndWheres[$idx], $dql);

                    return $qb;
                })
            ;
        }

        if ([] === $expectedParams) {
            $qb->expects(self::never())->method('setParameter');
        } else {
            $paramMatcher = self::exactly(\count($expectedParams));
            $qb->expects($paramMatcher)
                ->method('setParameter')
                ->willReturnCallback(static function (mixed $name, mixed $value) use ($paramMatcher, $expectedParams, $qb): QueryBuilder {
                    $idx = $paramMatcher->numberOfInvocations() - 1;
                    self::assertSame($expectedParams[$idx][0], $name, 'parameter name at index ' . $idx);
                    self::assertSame($expectedParams[$idx][1], $value, 'parameter value at index ' . $idx);

                    return $qb;
                })
            ;
        }

        return $qb;
    }

    /**
     * Build a minimal ClassMetadata stub that answers `hasField()` for
     * the given list of mapped fields. Avoids depending on a real
     * Doctrine bootstrap in unit tests.
     *
     * @param list<string> $fields
     *
     * @return ClassMetadata<object>
     */
    private function stubMetadata(array $fields): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasField')
            ->willReturnCallback(static fn (string $name): bool => \in_array($name, $fields, true))
        ;

        return $metadata;
    }
}
