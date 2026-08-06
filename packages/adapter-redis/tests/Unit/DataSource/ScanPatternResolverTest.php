<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Unit\DataSource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\DataSource\ScanPatternResolver;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;

/**
 * The resolver is the single implementation behind the four
 * key-scanning data sources' `scanPattern()` (string / list / set /
 * sorted-set) — before v0.10 each carried a byte-identical private
 * copy, three of which had zero test coverage.
 */
#[CoversClass(ScanPatternResolver::class)]
final class ScanPatternResolverTest extends TestCase
{
    private const PREFIX = 'app:flags:';

    #[Test]
    public function noIdFilterMatchesEverythingUnderThePrefix(): void
    {
        self::assertSame(
            'app:flags:*',
            ScanPatternResolver::resolve(new DataQuery('flags'), self::PREFIX),
        );
    }

    #[Test]
    public function likeNeedleWithoutGlobCharsIsWrappedAsContains(): void
    {
        $query = (new DataQuery('flags'))->withFilter(
            'id',
            new FilterCriterion('id', FilterOperator::Like, 'checkout'),
        );

        self::assertSame('app:flags:*checkout*', ScanPatternResolver::resolve($query, self::PREFIX));
    }

    #[Test]
    public function likeNeedleWithGlobCharsIsPassedThrough(): void
    {
        $star = (new DataQuery('flags'))->withFilter(
            'id',
            new FilterCriterion('id', FilterOperator::Like, 'checkout*'),
        );
        self::assertSame('app:flags:checkout*', ScanPatternResolver::resolve($star, self::PREFIX));

        $question = (new DataQuery('flags'))->withFilter(
            'id',
            new FilterCriterion('id', FilterOperator::Like, 'flag-?'),
        );
        self::assertSame('app:flags:flag-?', ScanPatternResolver::resolve($question, self::PREFIX));
    }

    #[Test]
    public function nonLikeOperatorOnIdDegradesToMatchEverything(): void
    {
        foreach ([FilterOperator::Eq, FilterOperator::In, FilterOperator::Between] as $operator) {
            $query = (new DataQuery('flags'))->withFilter(
                'id',
                new FilterCriterion('id', $operator, 'checkout'),
            );

            self::assertSame(
                'app:flags:*',
                ScanPatternResolver::resolve($query, self::PREFIX),
                $operator->name . ' must not narrow the SCAN pattern.',
            );
        }
    }

    #[Test]
    public function nonStringOrEmptyLikeValueDegradesToMatchEverything(): void
    {
        foreach ([42, '', null, ['x']] as $value) {
            $query = (new DataQuery('flags'))->withFilter(
                'id',
                new FilterCriterion('id', FilterOperator::Like, $value),
            );

            self::assertSame('app:flags:*', ScanPatternResolver::resolve($query, self::PREFIX));
        }
    }

    #[Test]
    public function nonIdFiltersAreIgnoredByTheScanPattern(): void
    {
        $query = (new DataQuery('flags'))->withFilter(
            'value',
            new FilterCriterion('value', FilterOperator::Like, 'on'),
        );

        self::assertSame('app:flags:*', ScanPatternResolver::resolve($query, self::PREFIX));
    }
}
