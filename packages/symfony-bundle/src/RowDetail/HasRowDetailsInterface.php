<?php

declare(strict_types=1);

namespace Polysource\Bundle\RowDetail;

use Polysource\Core\Query\DataRecord;
use Polysource\Core\RowDetail\RowDetail;

/**
 * Opt-in capability interface for expandable row details on the
 * native Polysource listing — the resource itself declares its
 * detail, mirroring the bridge's per-entity provider. Layered onto
 * `ResourceInterface` via `instanceof` (the `StyledActionInterface`
 * pattern) so the frozen v1.0 contract is untouched.
 *
 *     final class FailedMessageResource extends AbstractResource
 *         implements HasRowDetailsInterface
 *     {
 *         public function hasRowDetail(DataRecord $record): bool
 *         {
 *             return true;
 *         }
 *
 *         public function getRowDetail(DataRecord $record): ?RowDetail
 *         {
 *             return RowDetail::template('admin/message/_row_detail.html.twig');
 *         }
 *
 *         public function getRowDetailPermission(): ?string
 *         {
 *             return null;
 *         }
 *     }
 *
 * @since 1.1.0
 */
interface HasRowDetailsInterface
{
    /**
     * Whether THIS row shows an expansion control. Called once per
     * visible row while rendering the index — keep it cheap (a
     * property test, not a query). Rows without a detail render no
     * chevron.
     */
    public function hasRowDetail(DataRecord $record): bool;

    /**
     * Build the detail for one row. Called ONLY by the lazy
     * detail-panel endpoint — never during index rendering — so
     * heavier work (loading related data into the context) is safe
     * here. Returning `null` 404s the panel (row without detail).
     *
     * The template receives `record`, `resource` and the
     * {@see RowDetail} context.
     */
    public function getRowDetail(DataRecord $record): ?RowDetail;

    /**
     * Voter attribute checked with the {@see DataRecord} as subject
     * — before rendering the chevron (cosmetic) and on the endpoint
     * (authoritative). `null` = anyone who can view the resource.
     */
    public function getRowDetailPermission(): ?string;
}
