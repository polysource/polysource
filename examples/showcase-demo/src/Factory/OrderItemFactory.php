<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\OrderItem;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<OrderItem>
 */
final class OrderItemFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return OrderItem::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'product' => ProductFactory::randomOrCreate(),
            'quantity' => self::faker()->numberBetween(1, 4),
            'unitPriceCents' => self::faker()->numberBetween(500, 15000),
        ];
    }
}
