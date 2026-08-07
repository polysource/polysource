<?php

declare(strict_types=1);

namespace App\Polysource\RowDetail;

use App\Entity\Order;
use Polysource\EasyAdminFilterBridge\RowDetail\AbstractRowDetailProvider;

/**
 * v1.1.0 expandable row details demo: each order row expands into
 * its line items, loaded lazily when the chevron is clicked. The
 * service is picked up by autoconfiguration on
 * RowDetailProviderInterface — no extra wiring.
 */
final class OrderRowDetailProvider extends AbstractRowDetailProvider
{
    public function getSupportedEntity(): string
    {
        return Order::class;
    }

    protected function template(): string
    {
        return 'admin/order/_row_detail.html.twig';
    }
}
