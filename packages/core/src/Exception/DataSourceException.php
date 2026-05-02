<?php

declare(strict_types=1);

namespace Polysource\Core\Exception;

use RuntimeException;

/**
 * Base exception for any failure originating from a DataSource.
 *
 * Adapters should wrap their specific exceptions (Doctrine DBAL,
 * Redis, HTTP client, etc.) into this hierarchy so the upper layers
 * have a stable type to catch.
 */
class DataSourceException extends RuntimeException
{
}
