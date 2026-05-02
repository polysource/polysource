<?php

declare(strict_types=1);

namespace Polysource\Core\Exception;

/**
 * Thrown by adapters that don't support a specific operation.
 *
 * Examples:
 *  - A Messenger failed source can't do `count()` (the transport
 *    doesn't expose a total). It throws UnsupportedOperationException
 *    rather than returning a fake number.
 *  - A read-only source receiving a write call (rare — usually prevented
 *    by the absence of {@see \Polysource\Core\DataSource\WritableDataSourceInterface}).
 */
final class UnsupportedOperationException extends DataSourceException
{
    public static function forMethod(string $method, string $reason = ''): self
    {
        $msg = \sprintf('Operation "%s" is not supported by this data source.', $method);
        if ('' !== $reason) {
            $msg .= ' ' . $reason;
        }

        return new self($msg);
    }
}
