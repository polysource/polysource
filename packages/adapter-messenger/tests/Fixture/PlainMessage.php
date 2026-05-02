<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Fixture;

/**
 * Plain message — JSON-serialisable.
 */
final readonly class PlainMessage
{
    public function __construct(
        public string $name,
        public int $count,
    ) {
    }
}
