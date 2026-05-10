<?php

declare(strict_types=1);

namespace Polysource\Audit\Model;

/**
 * Outcome of an audited action — orthogonal to `ActionResult::$success`
 * (a bool) because we distinguish uncaught exceptions from gracefully
 * returned `ActionResult::failure()`.
 *
 * Keep enum simple (case-only, no methods) per project enum convention
 * convention — facilitates v0.5 storage migration if we need to swap
 * the `string` backing type later.
 */
enum AuditOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Exception = 'exception';
}
