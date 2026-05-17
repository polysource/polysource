<?php

declare(strict_types=1);

namespace Polysource\Filter\Url;

/**
 * Builds URL query strings carrying the `filters[...]` slice in the
 * shape EA's `crud/index` Controller honours:
 *
 *     filters[<prop>][comparison]=<op>&filters[<prop>][value]=<v>
 *
 * Before this builder, three places emitted near-identical query
 * shapes ad hoc (`CellFilterMenuExtension`, `SavedViewApplySubscriber`,
 * the inverse parser in `SavedViewController::buildCriteria`). The
 * v0.8.1 regression — `filters[X]=v` scalar shorthand rejected by EA
 * — came from one of those sites drifting from the others. A single
 * builder eliminates that class of bug.
 *
 * @since 0.9.0
 */
final class FilterUrlBuilder
{
    /**
     * Merge a single-criterion filter slice into an existing query
     * array. Returns the merged query — caller is responsible for
     * stringifying with `http_build_query()` and prefixing with a
     * path.
     *
     * The slice always uses the **expanded** EA shape
     * (`filters[<prop>][comparison]=<op>&filters[<prop>][value]=<v>`)
     * regardless of the operator. The scalar shorthand
     * (`filters[<prop>]=<v>`) is silently dropped by EA's filter
     * pipeline — applying it renders chips without filtering rows.
     * Dogfood signal #10 (2026-05-17, shipped in v0.8.1).
     *
     * @param array<int|string, mixed> $existingQuery the request query (`$request->query->all()`) — kept as-is except for the `filters[<property>]` slot which is overwritten
     * @param string                   $property      filter property name
     * @param string                   $eaOperator    EA URL operator (`=`, `!=`, `like`, …) — already in EA shape; for canonical operators pass through {@see OperatorMap::toEa()} first
     * @param string                   $value         stringified filter value
     * @param bool                     $replace       if true, drop every other filter from the existing query — used for "Show only this X" actions that reset the listing to a single criterion
     *
     * @return array<int|string, mixed>
     */
    public static function mergeCriterion(
        array $existingQuery,
        string $property,
        string $eaOperator,
        string $value,
        bool $replace = false,
    ): array {
        $query = $replace ? [] : $existingQuery;
        $filters = $replace ? [] : FilterArrayExtractor::fromQueryArray($query);

        $filters[$property] = [
            'comparison' => $eaOperator,
            'value' => $value,
        ];

        $query['filters'] = $filters;

        return $query;
    }

    /**
     * Stringify a query array into a path + RFC3986-encoded query
     * string. Empty query → bare path. Used by the cell-filter menu
     * to build relative `href` URLs that the browser resolves against
     * the current host.
     *
     * @param array<int|string, mixed> $query
     */
    public static function toPathWithQuery(string $path, array $query): string
    {
        $qs = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        return '' !== $qs ? $path . '?' . $qs : $path;
    }
}
