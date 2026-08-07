<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\RowDetail;

/**
 * Declares the expandable row detail for one Doctrine entity.
 *
 * Implementations are auto-registered via the
 * `polysource.row_detail_provider` tag (autoconfigured on this
 * interface). One provider per entity class; when several declare
 * the same class, the last registered wins — standard DI override
 * semantics, so hosts can replace a vendor-shipped provider.
 *
 * Minimal implementation via {@see AbstractRowDetailProvider}:
 *
 *     final class OrderRowDetailProvider extends AbstractRowDetailProvider
 *     {
 *         public function getSupportedEntity(): string
 *         {
 *             return Order::class;
 *         }
 *
 *         protected function template(): string
 *         {
 *             return 'admin/order/_row_detail.html.twig';
 *         }
 *     }
 *
 * @since 1.1.0
 */
interface RowDetailProviderInterface
{
    /**
     * @return class-string the Doctrine entity this provider serves
     */
    public function getSupportedEntity(): string;

    /**
     * Voter attribute checked with the ROW'S ENTITY as subject —
     * both before rendering the expand control and, authoritatively,
     * on the backend endpoint. Return `null` to make the detail
     * reachable by anyone who can reach the (host-firewalled) admin
     * routes. When an attribute is declared and no security layer is
     * wired, access fails closed.
     */
    public function getPermission(): ?string;

    /**
     * Build the detail for one row. Only called lazily — when the
     * user actually expands the row, never during index rendering.
     */
    public function getRowDetail(object $entity): RowDetail;
}
