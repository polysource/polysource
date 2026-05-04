<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

use Countable;

/**
 * Paged result set returned by {@see \Polysource\Core\DataSource\DataSourceInterface::search()}.
 *
 * Cf. ADR-002 — `$total` is nullable: `null` means "unknown" (cursor-based
 * source). The UI paginator must branch on this:
 *   - `$total !== null` → classic offset/limit pagination with "Page X / Y"
 *   - `$total === null` → cursor pagination via `$nextCursor` / `$prevCursor`
 */
final class DataPage
{
    /**
     * @param iterable<DataRecord> $items
     */
    public function __construct(
        public readonly iterable $items,
        public readonly ?int $total = null,
        public readonly ?string $nextCursor = null,
        public readonly ?string $prevCursor = null,
    ) {
    }

    /**
     * @return list<DataRecord>
     */
    public function asArray(): array
    {
        if (\is_array($this->items)) {
            return array_values($this->items);
        }

        // array_values + spread keeps the result as a `list` even when
        // keys would otherwise come through as strings or non-sequential
        // ints — PHPStan needs the cast to prove the list shape.
        return array_values([...$this->items]);
    }

    /**
     * Whether the page contains zero records.
     *
     * Note: for un-countable Traversable items where the total is unknown,
     * this materialises the iterator to determine emptiness — which may
     * consume it. Prefer checking $total when known.
     */
    public function isEmpty(): bool
    {
        if (\is_array($this->items)) {
            return [] === $this->items;
        }

        if ($this->items instanceof Countable) {
            return 0 === \count($this->items);
        }

        if (null !== $this->total) {
            return 0 === $this->total;
        }

        // Last resort: peek the iterator. Materialises it (one item).
        return [] === [...$this->items];
    }
}
