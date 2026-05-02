<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Direction of a sort criterion.
 *
 * Kept intentionally simple (case names only, no methods) to ease
 * migration to PHP 8.0+ in v0.5+ where this will become a class
 * with constants. Cf. ADR-007 §Migration.
 */
enum SortDirection: string
{
    case ASC = 'asc';
    case DESC = 'desc';
}
