<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch\Tests\InMemory;

use Polysource\Adapter\Meilisearch\Client\MeilisearchIndexInterface;

/**
 * Test-only {@see MeilisearchIndexInterface} backed by an in-memory
 * map. Implements just enough of Meilisearch's behaviour to exercise
 * the data source without spinning a real server.
 *
 * Search semantics (simplified):
 *  - Empty query string returns every document.
 *  - Non-empty query string filters documents whose JSON-serialised
 *    form contains the query (case-insensitive). Ranking ignored.
 *  - Filter expressions are parsed to a tiny subset:
 *      `field = 'value'`, `field IN ['a','b']`, `field > N`.
 *  - Sort: `field:asc` / `field:desc`.
 *  - Pagination: limit + offset.
 */
final class InMemoryMeilisearchIndex implements MeilisearchIndexInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $documents = [];

    public function __construct(private readonly string $primaryKey = 'id')
    {
    }

    public function search(string $query, array $options = []): array
    {
        $hits = array_values($this->documents);

        if ('' !== $query) {
            $needle = strtolower($query);
            $hits = array_values(array_filter(
                $hits,
                static function (array $doc) use ($needle): bool {
                    $haystack = strtolower((string) json_encode($doc));

                    return str_contains($haystack, $needle);
                },
            ));
        }

        $filter = $options['filter'] ?? '';
        if (\is_string($filter) && '' !== $filter) {
            $hits = array_values(array_filter($hits, fn (array $doc): bool => $this->matchesFilter($doc, $filter)));
        }

        $sort = $options['sort'] ?? [];
        if (\is_array($sort) && [] !== $sort) {
            /** @var list<string> $sortList */
            $sortList = array_values(array_filter($sort, static fn ($v): bool => \is_string($v)));
            if ([] !== $sortList) {
                usort($hits, fn (array $a, array $b): int => $this->compareForSort($a, $b, $sortList));
            }
        }

        $estimatedTotal = \count($hits);
        $offset = \is_int($options['offset'] ?? null) ? $options['offset'] : 0;
        $limit = \is_int($options['limit'] ?? null) ? $options['limit'] : 50;

        $hits = \array_slice($hits, $offset, $limit);

        return [
            'hits' => $hits,
            'estimatedTotalHits' => $estimatedTotal,
            'totalHits' => null,
        ];
    }

    public function getDocument(string $id): ?array
    {
        return $this->documents[$id] ?? null;
    }

    public function addDocuments(array $documents): void
    {
        foreach ($documents as $doc) {
            $id = $doc[$this->primaryKey] ?? null;
            if (\is_scalar($id)) {
                $this->documents[(string) $id] = $doc;
            }
        }
    }

    public function deleteDocument(string $id): void
    {
        unset($this->documents[$id]);
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function matchesFilter(array $doc, string $filter): bool
    {
        // Split on AND (we don't bother with OR / parentheses for tests).
        foreach (explode(' AND ', $filter) as $clause) {
            if (!$this->matchesClause($doc, trim($clause))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function matchesClause(array $doc, string $clause): bool
    {
        if (1 === preg_match('/^(\w+)\s+IN\s+\[(.+)\]$/', $clause, $m)) {
            $field = $m[1];
            $values = array_map(
                static fn (string $v): string => trim(trim($v), "'"),
                explode(',', $m[2]),
            );
            $needle = self::asString($doc[$field] ?? '');

            return \in_array($needle, $values, true);
        }

        if (1 === preg_match('/^(\w+)\s*(=|!=|>=|<=|>|<)\s*(.+)$/', $clause, $m)) {
            $field = $m[1];
            $op = $m[2];
            $rawValue = trim($m[3], "'");
            $actual = $doc[$field] ?? null;

            return match ($op) {
                '=' => self::asString($actual) === $rawValue,
                '!=' => self::asString($actual) !== $rawValue,
                '>' => is_numeric($actual) && is_numeric($rawValue) && $actual > (float) $rawValue,
                '>=' => is_numeric($actual) && is_numeric($rawValue) && $actual >= (float) $rawValue,
                '<' => is_numeric($actual) && is_numeric($rawValue) && $actual < (float) $rawValue,
                default => is_numeric($actual) && is_numeric($rawValue) && $actual <= (float) $rawValue,
            };
        }

        return true;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     * @param list<string>         $sort
     */
    private function compareForSort(array $a, array $b, array $sort): int
    {
        foreach ($sort as $expression) {
            [$field, $direction] = array_pad(explode(':', $expression, 2), 2, 'asc');
            $av = $a[$field] ?? null;
            $bv = $b[$field] ?? null;
            $cmp = $av <=> $bv;
            if (0 !== $cmp) {
                return 'desc' === $direction ? -$cmp : $cmp;
            }
        }

        return 0;
    }

    private static function asString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }
}
