<?php

declare(strict_types=1);

namespace Polysource\Filter\Url;

/**
 * Defensive extraction of the `filters` slice from an HTTP query
 * array. Before this helper the same defensive guard was repeated in
 * at least three places (`UrlFilterApplier::apply`,
 * `FilterShortUrlExtension::shortUrl`, `CellFilterMenuExtension::urlFor`)
 * each spelling out:
 *
 *     $filters = isset($query['filters']) && \is_array($query['filters'])
 *         ? $query['filters']
 *         : [];
 *
 * The guard isn't trivial — `$request->query->all()` may yield
 * scalar values under `filters` when a hostile client crafts
 * `?filters=foo` (no array). Centralising it ensures every consumer
 * gets the same well-typed shape.
 *
 * @since 0.9.0
 */
final class FilterArrayExtractor
{
    /**
     * Extract the `filters` slice from a request query array, returning
     * a defensively-typed associative array (string keys only — numeric
     * keys submitted by a hostile client are dropped).
     *
     * @param array<int|string, mixed> $query Typically `$request->query->all()`
     *
     * @return array<string, mixed>
     */
    public static function fromQueryArray(array $query): array
    {
        $raw = $query['filters'] ?? null;
        if (!\is_array($raw)) {
            return [];
        }

        $filters = [];
        foreach ($raw as $key => $value) {
            // Only string keys are valid filter property names. EA
            // and Polysource both submit string keys; numeric keys
            // would be a hostile-input shape or a developer mistake.
            if (\is_string($key) && '' !== $key) {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
