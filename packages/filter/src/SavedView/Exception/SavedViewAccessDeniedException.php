<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Exception;

use RuntimeException;

/**
 * Thrown by {@see \Polysource\Filter\SavedView\SavedViewService} when the
 * current actor lacks the voter attribute required for the requested
 * operation (`EDIT`, `DELETE`, or `SHARE` — `VIEW` denials surface as
 * a `null` return rather than an exception, matching `find()` semantics).
 *
 * Carries the voter attribute and the saved-view id so callers can
 * distinguish denial-by-policy from genuine runtime faults; bridges
 * (e.g. `polysource/easyadmin-filter-bridge`'s SavedViewController) can
 * map this onto a 403 response without leaking implementation details.
 *
 * Replaces the earlier bare {@see RuntimeException} thrown for the
 * same cases — the security-vs-runtime distinction was indistinguishable
 * to external catchers, surfaced by the pre-v0.1.0 code review.
 *
 * @since 0.1.0
 */
final class SavedViewAccessDeniedException extends RuntimeException
{
    public function __construct(
        public readonly string $attribute,
        public readonly string $savedViewId,
        ?string $message = null,
    ) {
        parent::__construct($message ?? \sprintf(
            'Access denied (%s) on saved view "%s".',
            $attribute,
            $savedViewId,
        ));
    }
}
