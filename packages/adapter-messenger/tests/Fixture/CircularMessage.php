<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Fixture;

/**
 * Message with a self-reference — exercises the var_export fallback in
 * {@see \Polysource\Adapter\Messenger\DataSource\EnvelopeMapper}. Recursive
 * references make json_encode throw.
 */
final class CircularMessage
{
    public self $loop;

    public function __construct()
    {
        $this->loop = $this;
    }
}
