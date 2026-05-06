<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\ProductFactory;
use Zenstruck\Foundry\Story;

/**
 * 200 products with realistic distribution: ~60% active, ~20% draft,
 * ~20% archived (driven by ProductFactory::STATUSES).
 */
final class CatalogStory extends Story
{
    public function build(): void
    {
        ProductFactory::createMany(200);
    }
}
