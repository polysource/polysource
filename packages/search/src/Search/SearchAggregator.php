<?php

declare(strict_types=1);

namespace Polysource\Search\Search;

use Throwable;

/**
 * Fans out a query across every {@see SearchProviderInterface}
 * tagged `polysource.search.provider`, merges the results, and
 * sorts them desc by score.
 *
 * Three contention layers protect the palette UX:
 *  1. Per-provider `$perProviderLimit` cap (default 5).
 *  2. Total wall-clock budget `$totalBudgetMs` (default 250) —
 *     after the deadline the next provider is skipped.
 *  3. Try/catch around each provider call — a single misbehaving
 *     provider is silently skipped rather than killing the
 *     palette.
 */
final class SearchAggregator
{
    public const DEFAULT_PER_PROVIDER_LIMIT = 5;
    public const DEFAULT_TOTAL_BUDGET_MS = 250.0;

    /**
     * @param iterable<SearchProviderInterface> $providers services tagged `polysource.search.provider`
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly int $perProviderLimit = self::DEFAULT_PER_PROVIDER_LIMIT,
        private readonly float $totalBudgetMs = self::DEFAULT_TOTAL_BUDGET_MS,
    ) {
    }

    /**
     * @return list<SearchResult>
     */
    public function aggregate(string $query): array
    {
        $trimmed = trim($query);
        if ('' === $trimmed) {
            return [];
        }

        $deadline = microtime(true) + ($this->totalBudgetMs / 1000.0);
        $merged = [];

        foreach ($this->providers as $provider) {
            if (microtime(true) > $deadline) {
                break;
            }
            try {
                foreach ($provider->search($trimmed, $this->perProviderLimit, $deadline) as $result) {
                    $merged[] = $result;
                }
            } catch (Throwable) {
                // contained: one bad provider doesn't break the palette
            }
        }

        usort($merged, static fn (SearchResult $a, SearchResult $b): int => $b->score <=> $a->score);

        return $merged;
    }
}
