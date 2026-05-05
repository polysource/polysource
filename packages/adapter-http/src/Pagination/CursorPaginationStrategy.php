<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Pagination;

use Polysource\Core\Query\DataQuery;

/**
 * Cursor-based pagination — best for high-volume APIs where total
 * counts are expensive (Stripe, Twitter, GitHub v4).
 *
 * The data source ships the cursor through `Pagination::offset`
 * (string-coerced) — same protocol abuse the Redis adapter uses to
 * avoid introducing a new pagination-payload type for v0.1.
 *
 * Default response shape:
 *
 *     { "data": [...], "next_cursor": "abc..." }
 *
 * Total is intentionally not parsed — cursor APIs typically don't
 * expose one (and ADR-002 says null is fine for cursor sources).
 */
final class CursorPaginationStrategy implements PaginationStrategyInterface
{
    public function __construct(
        private readonly string $cursorQueryParam = 'cursor',
        private readonly string $limitQueryParam = 'limit',
        private readonly string $itemsKey = 'data',
        private readonly string $nextCursorKey = 'next_cursor',
        private readonly int $defaultPageSize = 50,
    ) {
    }

    public function buildQueryParams(DataQuery $query): array
    {
        $pagination = $query->pagination;
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;

        $params = [$this->limitQueryParam => $limit];

        // Treat the offset as an opaque cursor token — non-zero means
        // "resume from here". The UI builds this from the previous
        // response's nextCursor.
        if (null !== $pagination && $pagination->offset > 0) {
            $params[$this->cursorQueryParam] = (string) $pagination->offset;
        }

        return $params;
    }

    public function parseResponse(array $body, array $headers): array
    {
        unset($headers);
        $items = self::dig($body, $this->itemsKey);
        $nextCursor = self::dig($body, $this->nextCursorKey);

        return [
            'items' => self::asListOfMaps($items),
            'total' => null,
            'nextCursor' => \is_string($nextCursor) && '' !== $nextCursor ? $nextCursor : null,
            'prevCursor' => null,
        ];
    }

    /**
     * @param array<string, mixed>|list<mixed> $body
     */
    private static function dig(array $body, string $dottedKey): mixed
    {
        $segments = explode('.', $dottedKey);
        $current = $body;
        foreach ($segments as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function asListOfMaps(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $row) {
            if (\is_array($row)) {
                /** @var array<string, mixed> $row */
                $items[] = $row;
            }
        }

        return $items;
    }
}
