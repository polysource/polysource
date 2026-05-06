<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Product;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Product>
 */
final class ProductFactory extends PersistentProxyObjectFactory
{
    private const CATEGORIES = [
        'apparel',
        'home-garden',
        'electronics',
        'beauty',
        'sports',
        'books',
        'kitchen',
        'kids',
    ];

    private const STATUSES = [
        Product::STATUS_ACTIVE,
        Product::STATUS_ACTIVE,
        Product::STATUS_ACTIVE,
        Product::STATUS_DRAFT,
        Product::STATUS_ARCHIVED,
    ];

    public static function class(): string
    {
        return Product::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        $name = ucfirst(self::faker()->unique()->words(self::faker()->numberBetween(2, 4), true));
        $slugger = new AsciiSlugger();
        $createdAt = \DateTimeImmutable::createFromMutable(
            self::faker()->dateTimeBetween('-18 months', '-1 day')
        );

        return [
            'sku' => 'SKU-'.strtoupper(self::faker()->unique()->bothify('??##??##')),
            'name' => $name,
            'slug' => $slugger->slug($name)->lower()->toString().'-'.self::faker()->randomNumber(4),
            'description' => self::faker()->paragraphs(2, true),
            'priceCents' => self::faker()->numberBetween(500, 25000),
            'currency' => 'EUR',
            'stock' => self::faker()->numberBetween(0, 250),
            'status' => self::faker()->randomElement(self::STATUSES),
            'category' => self::faker()->randomElement(self::CATEGORIES),
            'photoPath' => null,
            'createdAt' => $createdAt,
            'updatedAt' => $createdAt,
        ];
    }
}
