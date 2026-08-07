<?php

declare(strict_types=1);

namespace Polysource\Core\RowDetail;

/**
 * Value object describing what to render inside an expanded row —
 * a Twig template plus its context. Returned by the bridge's
 * `RowDetailProviderInterface::getRowDetail()` and the bundle's
 * `HasRowDetailsInterface::getRowDetail()`.
 *
 * Lives in core (a template *name* is an opaque string here — no
 * Twig dependency) so both downstream packages share one shape,
 * per the ADR-018 ≥2-consumers budget rule.
 *
 * The template always receives the row's entity as `entity` on top
 * of the given context (a `entity` key in `$context` wins, for
 * providers that need to alias it).
 *
 * Deliberately template-only for now: "a few extra fields", "a
 * custom panel" and "a table of related records" are all Twig
 * templates from the renderer's point of view. Richer content types
 * (a nested Polysource listing with its own filters/pagination)
 * are a planned follow-up and will extend this VO, not replace it.
 *
 * @since 1.1.0
 */
final class RowDetail
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $listingFilters
     */
    private function __construct(
        public readonly ?string $template,
        public readonly array $context = [],
        public readonly ?string $listingResource = null,
        public readonly array $listingFilters = [],
        public readonly ?int $listingPageSize = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context extra variables for the template
     */
    public static function template(string $template, array $context = []): self
    {
        return new self($template, $context);
    }

    /**
     * Embed another Polysource listing as the row's detail — the
     * master/detail case ("this order's items", "this transport's
     * messages"). Rendered read-only (table + pagination, no
     * actions/bulk/chevrons) by the bundle's embedded-listing
     * renderer; requires `polysource/symfony-bundle` with the child
     * resource registered.
     *
     * `$parentFilters` scopes the child listing to the expanded row:
     * a `property => value` map turned into equality criteria, so
     * the child's data source must accept those filter properties
     * (e.g. Doctrine's `allowedFilters` whitelist).
     *
     *     public function getRowDetail(DataRecord $record): ?RowDetail
     *     {
     *         return RowDetail::listing('order-items', [
     *             'orderId' => $record->identifier,
     *         ]);
     *     }
     *
     * @param array<string, mixed> $parentFilters property => value equality scoping
     * @param int|null             $pageSize      rows per embedded page (renderer default when null)
     *
     * @since 1.1.0
     */
    public static function listing(string $resourceName, array $parentFilters = [], ?int $pageSize = null): self
    {
        return new self(
            template: null,
            listingResource: $resourceName,
            listingFilters: $parentFilters,
            listingPageSize: $pageSize,
        );
    }

    public function isListing(): bool
    {
        return null !== $this->listingResource;
    }
}
