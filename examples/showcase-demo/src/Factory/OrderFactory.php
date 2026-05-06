<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Order;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Order>
 *
 * Default state = `paid`. Workflow distribution is enforced by
 * {@see App\Story\OrdersStory} which calls the `withStatus()` helpers
 * to obtain a realistic spread across all 7 workflow states.
 */
final class OrderFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Order::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $createdAt = \DateTimeImmutable::createFromMutable(
            self::faker()->dateTimeBetween('-12 months', 'now')
        );

        return [
            'reference' => 'ORD-'.strtoupper(self::faker()->unique()->bothify('??####??')),
            'status' => Order::STATUS_PAID,
            'customer' => CustomerFactory::randomOrCreate(),
            'totalCents' => self::faker()->numberBetween(2000, 80000),
            'currency' => 'EUR',
            'shippingAddress' => self::faker()->address(),
            'paymentTransactionId' => 'tx_'.self::faker()->bothify('############'),
            'trackingNumber' => null,
            'createdAt' => $createdAt,
            'paidAt' => $createdAt->modify('+5 minutes'),
        ];
    }
}
