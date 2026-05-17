<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Url;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Url\FilterArrayExtractor;

#[CoversClass(FilterArrayExtractor::class)]
final class FilterArrayExtractorTest extends TestCase
{
    #[Test]
    public function extractsStringKeyedFilters(): void
    {
        $extracted = FilterArrayExtractor::fromQueryArray([
            'filters' => ['status' => 'paid', 'name' => 'Foo'],
            'page' => '2',
        ]);

        self::assertSame(['status' => 'paid', 'name' => 'Foo'], $extracted);
    }

    #[Test]
    public function returnsEmptyArrayWhenFiltersKeyMissing(): void
    {
        self::assertSame([], FilterArrayExtractor::fromQueryArray(['page' => '2']));
    }

    #[Test]
    public function returnsEmptyArrayWhenFiltersIsScalar(): void
    {
        // Hostile input: ?filters=foo — a scalar value under the
        // filters key. Without the guard, downstream consumers would
        // crash trying to iterate over a string.
        self::assertSame([], FilterArrayExtractor::fromQueryArray(['filters' => 'foo']));
    }

    #[Test]
    public function dropsNumericKeyEntries(): void
    {
        // Hostile input: ?filters[]=foo — numeric keys are never
        // valid filter property names. Drop silently rather than
        // expose downstream to a "numeric string property" footgun.
        $extracted = FilterArrayExtractor::fromQueryArray([
            'filters' => [0 => 'foo', 'status' => 'paid', 5 => 'bar'],
        ]);

        self::assertSame(['status' => 'paid'], $extracted);
    }

    #[Test]
    public function dropsEmptyStringKey(): void
    {
        $extracted = FilterArrayExtractor::fromQueryArray([
            'filters' => ['' => 'orphan', 'status' => 'paid'],
        ]);

        self::assertSame(['status' => 'paid'], $extracted);
    }

    #[Test]
    public function preservesArrayValues(): void
    {
        // Filter values can be arrays (EA expanded shape with
        // comparison + value envelope, or list shape for IN). The
        // extractor doesn't sanity-check value shapes — just the keys.
        $extracted = FilterArrayExtractor::fromQueryArray([
            'filters' => [
                'status' => ['comparison' => '=', 'value' => 'paid'],
                'country' => ['FR', 'DE'],
            ],
        ]);

        self::assertSame([
            'status' => ['comparison' => '=', 'value' => 'paid'],
            'country' => ['FR', 'DE'],
        ], $extracted);
    }
}
