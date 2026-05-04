<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Fixture;

/**
 * Plain message — JSON-serialisable.
 */
final class PlainMessage
{
    public function __construct(
        public readonly string $name,
        public readonly int $count,
    ) {
    }
}
