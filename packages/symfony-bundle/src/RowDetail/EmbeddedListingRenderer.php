<?php

declare(strict_types=1);

namespace Polysource\Bundle\RowDetail;

use Polysource\Bundle\Controller\ControllerSupport;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\Pagination;
use Polysource\Core\RowDetail\RowDetail;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the view variables for a listing-type {@see RowDetail} —
 * the "another Polysource listing as row detail" case (v1.1.0).
 *
 * Deliberately NOT a second pass through IndexController /
 * AdminContextResolver: the embedded listing is a plain read of the
 * child resource, so no second AdminContext is created and the
 * request-scoped context provider (one slot per request, cf. the
 * v0.1 note in AdminContextResolver) is never touched. Each expanded
 * row fetches its own panel URL, so one HTTP request only ever hosts
 * ONE embedded listing — pagination rides on dedicated `rd_page` /
 * `rd_limit` params of that panel URL with nothing to collide with.
 *
 * The embedded view is read-only per ADR-028: table + pager, no
 * inline actions, no bulk, no nested chevrons (which also rules out
 * detail-in-detail recursion).
 *
 * @since 1.1.0
 */
final class EmbeddedListingRenderer
{
    public const TEMPLATE = '@Polysource/embed/listing.html.twig';

    /** Query param carrying the embedded page number (1-based). */
    public const PARAM_PAGE = 'rd_page';

    public const DEFAULT_PAGE_SIZE = 10;

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly ControllerSupport $support,
    ) {
    }

    /**
     * Variables for {@see self::TEMPLATE}. Throws the registry's
     * not-found on an unknown resource name and ControllerSupport's
     * AccessDeniedHttpException when the current user may not view
     * the child resource — both surface as errors of the panel
     * request only, never of the outer listing.
     *
     * @return array<string, mixed>
     */
    public function buildView(RowDetail $detail, Request $request): array
    {
        \assert(null !== $detail->listingResource);

        $resource = $this->resources->get($detail->listingResource);
        $this->support->assertResourceAccess($resource);

        $pageSize = $detail->listingPageSize ?? self::DEFAULT_PAGE_SIZE;
        $pageNumber = max(1, $request->query->getInt(self::PARAM_PAGE, 1));

        $filters = [];
        foreach ($detail->listingFilters as $property => $value) {
            $filters[$property] = new FilterCriterion($property, FilterOperator::Eq, $value);
        }

        $query = new DataQuery(
            resourceName: $detail->listingResource,
            filters: $filters,
            pagination: new Pagination(offset: ($pageNumber - 1) * $pageSize, limit: $pageSize),
        );

        $page = $resource->getDataSource()->search($query);
        $records = $page->asArray();

        $fields = $this->support->collectFields($resource, 'index');
        if ([] === $fields && [] !== $records) {
            $fields = ControllerSupport::synthesiseFieldsFromRecord($records[0]);
        }

        $totalPages = null !== $page->total && $pageSize > 0
            ? max(1, (int) ceil($page->total / $pageSize))
            : null;

        return [
            'embed_resource' => $resource,
            'embed_records' => $records,
            'embed_fields' => $fields,
            'embed_total' => $page->total,
            'embed_page_number' => $pageNumber,
            'embed_total_pages' => $totalPages,
            'embed_page_param' => self::PARAM_PAGE,
            // Pager links rebuild the CURRENT panel URL with only the
            // embedded page param swapped — fragment=1 and the outer
            // route params ride along untouched.
            'embed_base_query' => array_diff_key($request->query->all(), [self::PARAM_PAGE => null]),
            'embed_path' => $request->getPathInfo(),
        ];
    }
}
