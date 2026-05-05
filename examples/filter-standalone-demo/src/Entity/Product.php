<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Entity;

use DateTimeImmutable;

/**
 * Plain product POPO — no Doctrine, no annotations. The whole point
 * of this demo is to prove polysource/filter works on a vanilla
 * Symfony stack with no admin framework, no ORM coupling.
 */
final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $category,
        public readonly float $price,
        public readonly bool $isAvailable,
        public readonly DateTimeImmutable $releasedAt,
    ) {
    }
}
