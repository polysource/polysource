<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Pagination;

use Polysource\Core\Query\DataQuery;

/**
 * Page-number-based pagination — the most common shape for REST
 * APIs (`?page=2&per_page=20`, total either in the body envelope
 * or as a header).
 *
 * Default config matches a JSON envelope of:
 *
 *     { "data": [...], "meta": { "total": 123 } }
 *
 * Hosts override the property paths via the constructor for APIs
 * with different conventions (e.g. WordPress REST: `posts` array
 * + `X-WP-Total` header).
 */
final class PageNumberPaginationStrategy implements PaginationStrategyInterface
{
    public function __construct(
        private readonly string $pageQueryParam = 'page',
        private readonly string $perPageQueryParam = 'per_page',
        private readonly string $itemsKey = 'data',
        private readonly string $totalKey = 'meta.total',
        private readonly int $defaultPageSize = 50,
    ) {
    }

    public function buildQueryParams(DataQuery $query): array
    {
        $pagination = $query->pagination;
        $limit = null === $pagination ? $this->defaultPageSize : $pagination->limit;
        $offset = null === $pagination ? 0 : $pagination->offset;
        $page = (int) floor($offset / max($limit, 1)) + 1;

        return [
            $this->pageQueryParam => $page,
            $this->perPageQueryParam => $limit,
        ];
    }

    public function parseResponse(array $body, array $headers): array
    {
        unset($headers);
        $items = self::dig($body, $this->itemsKey);
        $totalRaw = self::dig($body, $this->totalKey);

        return [
            'items' => self::asListOfMaps($items),
            'total' => \is_int($totalRaw) ? $totalRaw : (is_numeric($totalRaw) ? (int) $totalRaw : null),
            'nextCursor' => null,
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
