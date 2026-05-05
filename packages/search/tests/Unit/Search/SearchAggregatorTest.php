<?php

declare(strict_types=1);

namespace Polysource\Search\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Polysource\Search\Search\SearchAggregator;
use Polysource\Search\Search\SearchProviderInterface;
use Polysource\Search\Search\SearchResult;
use RuntimeException;

final class SearchAggregatorTest extends TestCase
{
    public function testEmptyQueryReturnsEmpty(): void
    {
        $agg = new SearchAggregator([new RecordingProvider('p1', 'P1', [])]);
        self::assertSame([], $agg->aggregate(''));
        self::assertSame([], $agg->aggregate('   '));
    }

    public function testFanOutAcrossProviders(): void
    {
        $a = new RecordingProvider('a', 'A', [
            new SearchResult('a:1', 'Apple', '/a/1', 'A', score: 0.5),
        ]);
        $b = new RecordingProvider('b', 'B', [
            new SearchResult('b:1', 'Banana', '/b/1', 'B', score: 0.9),
            new SearchResult('b:2', 'Berry', '/b/2', 'B', score: 0.7),
        ]);

        $agg = new SearchAggregator([$a, $b]);
        $results = $agg->aggregate('search-text');

        // Sorted desc by score: b:1 (0.9), b:2 (0.7), a:1 (0.5)
        self::assertSame(['b:1', 'b:2', 'a:1'], array_map(static fn (SearchResult $r): string => $r->id, $results));

        self::assertSame('search-text', $a->lastQuery);
        self::assertSame('search-text', $b->lastQuery);
    }

    public function testThrowingProviderIsSilentlySkipped(): void
    {
        $a = new RecordingProvider('a', 'A', [new SearchResult('a:1', 'A1', '/a/1', 'A')]);
        $broken = new ThrowingProvider();
        $b = new RecordingProvider('b', 'B', [new SearchResult('b:1', 'B1', '/b/1', 'B')]);

        $agg = new SearchAggregator([$a, $broken, $b]);
        $results = $agg->aggregate('q');

        self::assertCount(2, $results);
        self::assertSame(['a:1', 'b:1'], array_map(static fn (SearchResult $r): string => $r->id, $results));
    }

    public function testTrimsWhitespaceBeforePassingToProviders(): void
    {
        $a = new RecordingProvider('a', 'A', []);
        $agg = new SearchAggregator([$a]);
        $agg->aggregate("  hello  \n");

        self::assertSame('hello', $a->lastQuery);
    }

    public function testPerProviderLimitIsForwarded(): void
    {
        $a = new RecordingProvider('a', 'A', []);
        $agg = new SearchAggregator([$a], perProviderLimit: 7);
        $agg->aggregate('q');

        self::assertSame(7, $a->lastLimit);
    }
}

final class RecordingProvider implements SearchProviderInterface
{
    public string $lastQuery = '';
    public int $lastLimit = 0;

    /**
     * @param list<SearchResult> $results
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly array $results,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function search(string $query, int $limit, float $deadline): array
    {
        $this->lastQuery = $query;
        $this->lastLimit = $limit;

        return $this->results;
    }
}

final class ThrowingProvider implements SearchProviderInterface
{
    public function getId(): string
    {
        return 'throwing';
    }

    public function getLabel(): string
    {
        return 'Throwing';
    }

    public function search(string $query, int $limit, float $deadline): array
    {
        throw new RuntimeException('boom');
    }
}
