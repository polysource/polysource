<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Fixture;

use Closure;

/**
 * Message containing a Closure — exercises the var_export fallback in
 * {@see \Polysource\Adapter\Messenger\DataSource\EnvelopeMapper}. Closures
 * cannot be JSON-encoded; json_encode throws.
 */
final class ClosureMessage
{
    public function __construct(
        public Closure $callback,
    ) {
    }
}
