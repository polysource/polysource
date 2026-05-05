<?php

declare(strict_types=1);

namespace Polysource\Search\Search;

use InvalidArgumentException;

/**
 * One Cmd+K palette row — a navigable hit returned by a
 * {@see SearchProviderInterface}.
 *
 * Score is provider-defined: each backend uses its own scale
 * (cosine similarity for Meilisearch, BM25 for Postgres,
 * Levenshtein for naive providers, or a flat 1.0 when no scoring
 * is available). The aggregator sorts results desc by score
 * without normalising — the global ordering is informative, not
 * absolute.
 *
 * Convention for `id`: prefix with the resource name to avoid
 * collisions when two resources expose records with the same
 * primary key (`orders:42` vs `users:42`).
 */
final class SearchResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $href,
        public readonly string $resourceName,
        public readonly ?string $icon = null,
        public readonly ?string $hint = null,
        public readonly float $score = 1.0,
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('SearchResult id cannot be empty.');
        }
        if ('' === $label) {
            throw new InvalidArgumentException('SearchResult label cannot be empty.');
        }
        if ('' === $href) {
            throw new InvalidArgumentException('SearchResult href cannot be empty.');
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('SearchResult resourceName cannot be empty.');
        }
        if ($score < 0.0) {
            throw new InvalidArgumentException(\sprintf('SearchResult score must be >= 0, got %f.', $score));
        }
    }
}
