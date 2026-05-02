<?php

declare(strict_types=1);

namespace Polysource\Core\Exception;

/**
 * Thrown when a resource name has no registered handler.
 *
 * NOT thrown when a {@see \Polysource\Core\Query\DataRecord} doesn't exist —
 * for that case, {@see \Polysource\Core\DataSource\DataSourceInterface::find()}
 * returns null.
 */
final class ResourceNotFoundException extends DataSourceException
{
    public static function forName(string $name): self
    {
        return new self(\sprintf('No Polysource resource registered under name "%s".', $name));
    }
}
