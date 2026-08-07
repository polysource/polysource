# Expandable row details (native listing)

*Since v1.1.0 — `polysource/symfony-bundle`*

Native Polysource listings (any adapter: Doctrine, Messenger, Redis,
Flysystem/S3, HTTP, Meilisearch) can expand a row in place to show
lazily-loaded detail content. For the EasyAdmin bridge variant, see
[the bridge guide](./easyadmin-filter-bridge/row-details.md).

## Opt in on the resource

Implement `HasRowDetailsInterface` — the feature is per-resource and
per-row:

```php
use Polysource\Bundle\RowDetail\HasRowDetailsInterface;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\RowDetail\RowDetail;

final class FailedMessageResource extends AbstractResource implements HasRowDetailsInterface
{
    // Cheap per-row gate, called while rendering the index.
    // Rows returning false get no chevron.
    public function hasRowDetail(DataRecord $record): bool
    {
        return true;
    }

    // Called ONLY when the user expands the row — heavier work
    // (context building, related loads) is safe here.
    public function getRowDetail(DataRecord $record): ?RowDetail
    {
        return RowDetail::template('admin/message/_row_detail.html.twig', [
            'stack_trace' => $record->get('trace'),
        ]);
    }

    // Voter attribute checked with the DataRecord as subject —
    // cosmetic gate on the chevron, authoritative on the endpoint.
    public function getRowDetailPermission(): ?string
    {
        return null;
    }
}
```

The chevron appears automatically in the actions column; the
endpoint is the generated `GET {prefix}/{slug}/{id}/detail-panel`
route (fifth route of every resource). Without JavaScript the
chevron navigates to a standalone page rendering the same content
inside the Polysource layout ([ADR-027](../adr/0027-progressive-enhancement.md));
with the `polysource--row-details` Stimulus controller (shipped by
`polysource/filter`, auto-registered via AssetMapper) it expands in
place with loading / error / retry states and client-side caching.

The template receives `record`, `resource`, `context`, plus your
`RowDetail` context.

## Embedded listing as detail

`RowDetail::listing()` renders **another registered Polysource
resource** as the row's detail — the master/detail case across any
datasource combination (a server's services, a transport's messages,
a bucket prefix's objects):

```php
public function getRowDetail(DataRecord $record): ?RowDetail
{
    return RowDetail::listing('order-items', [
        'orderId' => $record->identifier,   // property => value equality scoping
    ], pageSize: 10);
}
```

Mechanics and boundaries:

- The child's data source must accept the scoping properties as
  filters (Doctrine: whitelist them in `allowedFilters`).
- The embedded view is **read-only**: table + pagination, no inline
  actions, no bulk, no nested chevrons
  ([ADR-028](../adr/0028-scope-discipline.md) — and no
  detail-in-detail recursion).
- The current user must be granted the child resource's own view
  permission — embedding never bypasses it.
- Pagination uses a dedicated `rd_page` parameter **on the panel
  URL**. Each expanded row is its own HTTP request, so embedded
  paging can never collide with the outer listing's page, sort or
  filters. The pager works as plain links on the no-JS standalone
  page and refreshes the panel in place when injected.
- Sorting and user filters inside the embedded listing are not part
  of v1.1 — scoping comes from `parentFilters` only.

## Refresh behavior

The first response is cached client-side; closing and reopening does
not refetch. Volatile content can opt into refetching — on the
bridge this is `Polysource::rowDetail()->reloadOnOpen()`; on the
native theme the chevron template sets
`data-polysource--row-details-reload-value="true"` via a template
override of the `table_row_actions` block.

## Template override points

`@Polysource/index.html.twig` exposes named blocks since v1.1.0 —
`table_body_row`, `table_row_cells`, `table_row_actions` — so a host
can restyle the chevron or the whole row without copying the full
`content` block.
