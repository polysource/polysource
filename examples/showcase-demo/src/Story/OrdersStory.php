<?php

declare(strict_types=1);

namespace App\Story;

use App\Entity\Order;
use App\Factory\OrderFactory;
use App\Factory\OrderItemFactory;
use App\Factory\RefundFactory;
use Zenstruck\Foundry\Story;

/**
 * 1000 orders distributed across the 7 OrderWorkflow states with a
 * realistic skew (most are delivered, a few are still in cart, some
 * cancelled, very few refunded).
 *
 * Each order also gets 1-4 OrderItem rows, and ~6% of refundable
 * orders have a Refund attached so the EA Refund CRUD has data.
 */
final class OrdersStory extends Story
{
    /**
     * Status → relative weight. Total normalised to 1000 orders.
     */
    private const DISTRIBUTION = [
        Order::STATUS_DELIVERED => 580,
        Order::STATUS_SHIPPED => 130,
        Order::STATUS_PREPARING => 90,
        Order::STATUS_PAID => 80,
        Order::STATUS_CART => 60,
        Order::STATUS_CANCELLED => 40,
        Order::STATUS_REFUNDED => 20,
    ];

    public function build(): void
    {
        foreach (self::DISTRIBUTION as $status => $count) {
            for ($i = 0; $i < $count; ++$i) {
                $order = OrderFactory::createOne([
                    'status' => $status,
                ]);

                $entity = $order->_real();
                $this->stampLifecycle($entity, $status);

                // 1-4 line items per order (ProductFactory random).
                $itemsCount = random_int(1, 4);
                $total = 0;
                for ($k = 0; $k < $itemsCount; ++$k) {
                    $item = OrderItemFactory::createOne(['order' => $entity]);
                    $total += $item->_real()->getTotalCents();
                }

                $entity->setTotalCents($total);

                if ($status === Order::STATUS_REFUNDED || ($status === Order::STATUS_DELIVERED && random_int(1, 100) <= 6)) {
                    RefundFactory::createOne([
                        'order' => $entity,
                        'amountCents' => (int) round($total * (random_int(40, 100) / 100)),
                    ]);
                }
            }
        }
    }

    private function stampLifecycle(Order $order, string $status): void
    {
        $createdAt = $order->getCreatedAt();
        $paidAt = $createdAt->modify('+' . random_int(2, 60) . ' minutes');
        $preparingAt = $paidAt->modify('+' . random_int(1, 24) . ' hours');
        $shippedAt = $preparingAt->modify('+' . random_int(1, 48) . ' hours');
        $deliveredAt = $shippedAt->modify('+' . random_int(1, 5) . ' days');

        match ($status) {
            Order::STATUS_CART => null,
            Order::STATUS_PAID => $order->setPaidAt($paidAt),
            Order::STATUS_PREPARING => $order->setPaidAt($paidAt),
            Order::STATUS_SHIPPED => $order
                ->setPaidAt($paidAt)
                ->setShippedAt($shippedAt)
                ->setTrackingNumber('DHL' . random_int(100000000, 999999999)),
            Order::STATUS_DELIVERED => $order
                ->setPaidAt($paidAt)
                ->setShippedAt($shippedAt)
                ->setDeliveredAt($deliveredAt)
                ->setTrackingNumber('DHL' . random_int(100000000, 999999999)),
            Order::STATUS_CANCELLED => $order
                ->setCancelledAt($paidAt->modify('+' . random_int(10, 240) . ' minutes')),
            Order::STATUS_REFUNDED => $order
                ->setPaidAt($paidAt)
                ->setShippedAt($shippedAt)
                ->setDeliveredAt($deliveredAt)
                ->setRefundedAt($deliveredAt->modify('+' . random_int(2, 14) . ' days'))
                ->setTrackingNumber('DHL' . random_int(100000000, 999999999)),
            default => null,
        };
    }
}
