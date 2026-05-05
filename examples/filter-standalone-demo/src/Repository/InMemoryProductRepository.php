<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Repository;

use DateTimeImmutable;
use Polysource\Demo\FilterStandalone\Entity\Product;

/**
 * Hard-coded fixture — 12 products spread across 4 categories with
 * a variety of prices, release dates, and availability so each
 * filter (multi-select / between / date / boolean) has visible
 * effects. Replaces a Doctrine repo for the demo so a visitor can
 * `git clone && make install && make serve` with no DB setup.
 */
final class InMemoryProductRepository
{
    /**
     * @return list<Product>
     */
    public function findAll(): array
    {
        $d = static fn (string $iso): DateTimeImmutable => new DateTimeImmutable($iso);

        return [
            new Product(1, 'Mechanical keyboard', 'Tech', 159.0, true, $d('2024-09-12')),
            new Product(2, 'Wireless mouse', 'Tech', 49.0, true, $d('2025-02-04')),
            new Product(3, 'USB-C dock', 'Tech', 189.0, false, $d('2023-06-21')),
            new Product(4, 'Mid-century cookbook', 'Books', 34.0, true, $d('2024-11-30')),
            new Product(5, 'Domain-driven design', 'Books', 52.0, true, $d('2003-08-22')),
            new Product(6, 'Refactoring 2nd ed.', 'Books', 45.0, false, $d('2018-11-19')),
            new Product(7, 'Linen shirt', 'Apparel', 89.0, true, $d('2025-04-01')),
            new Product(8, 'Wool sweater', 'Apparel', 119.0, true, $d('2024-10-08')),
            new Product(9, 'Canvas tote', 'Apparel', 29.0, true, $d('2025-03-12')),
            new Product(10, 'Espresso machine', 'Home', 499.0, true, $d('2025-01-20')),
            new Product(11, 'Cast-iron pan', 'Home', 89.0, true, $d('2024-07-15')),
            new Product(12, 'Linen tablecloth', 'Home', 69.0, false, $d('2024-12-01')),
        ];
    }
}
