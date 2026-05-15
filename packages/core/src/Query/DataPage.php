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
     * ## ⚠️ Generator consumption warning (closes ADR-011 item L4)
     *
     * When `$items` is a one-shot `\Generator` (or any non-Countable
     * Traversable) AND `$total` is null (cursor source with unknown
     * total), this method MUST materialise the iterator to determine
     * emptiness. **The iterator is then consumed**: a subsequent call
     * to `asArray()` or `foreach ($page->items as ...)` will yield
     * zero rows.
     *
     * ## Safe call patterns
     *
     * 1. **Set `$total` explicitly when you know it** — `isEmpty()`
     *    short-circuits on `$total === 0` without touching items.
     * 2. **Materialise upstream** with `iterator_to_array($gen, false)`
     *    if you need both `isEmpty()` and iteration.
     * 3. **Use array or `\Countable` items** — both are always safe
     *    to call `isEmpty()` on.
     *
     * The unsafe last-resort branch exists because returning `false`
     * (= "non-empty") for an unknown-total Generator would make UI
     * rendering branch incorrectly. Materialising is the lesser evil —
     * but producers SHOULD pass `$total` to avoid hitting it.
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

        // Last resort: materialises the iterator. See method docblock —
        // producers SHOULD pass `$total` to avoid hitting this branch.
        return [] === [...$this->items];
    }
}
