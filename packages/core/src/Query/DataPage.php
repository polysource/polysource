<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Paged result set returned by {@see \Polysource\Core\DataSource\DataSourceInterface::search()}.
 *
 * Cf. ADR-002 — `$total` is nullable: `null` means "unknown" (cursor-based
 * source). The UI paginator must branch on this:
 *   - `$total !== null` → classic offset/limit pagination with "Page X / Y"
 *   - `$total === null` → cursor pagination via `$nextCursor` / `$prevCursor`
 *
 * @template TItem of DataRecord
 */
final readonly class DataPage
{
    /**
     * @param iterable<TItem> $items
     */
    public function __construct(
        public iterable $items,
        public ?int $total = null,
        public ?string $nextCursor = null,
        public ?string $prevCursor = null,
    ) {
    }

    /**
     * @return list<DataRecord>
     */
    public function asArray(): array
    {
        return \is_array($this->items) ? \array_values($this->items) : \iterator_to_array($this->items, false);
    }

    public function isEmpty(): bool
    {
        if (\is_array($this->items)) {
            return [] === $this->items;
        }

        if ($this->items instanceof \Countable) {
            return 0 === \count($this->items);
        }

        return null !== $this->total ? 0 === $this->total : !$this->items->getIterator()->valid();
    }
}
