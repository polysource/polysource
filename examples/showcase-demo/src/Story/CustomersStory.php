<?php

declare(strict_types=1);

namespace App\Story;

use App\Factory\CustomerFactory;
use Zenstruck\Foundry\Story;

/**
 * 500 customers spread across 8 European countries (per CustomerFactory).
 */
final class CustomersStory extends Story
{
    public function build(): void
    {
        self::buildBucket(self::class, 500);
    }

    public static function buildBucket(string $storyClass, int $count): void
    {
        CustomerFactory::createMany($count);
    }
}
