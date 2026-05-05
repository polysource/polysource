<?php

declare(strict_types=1);

namespace Polysource\Bundle\Event;

use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Dispatched immediately *after* an action's callable returns (or
 * throws) from inside `ActionController::safelyRun()`.
 *
 * The event always fires (success path, graceful failure path,
 * uncaught exception path) — listeners get a uniform observation
 * point regardless of how the action ended. The `exception`
 * property is non-null only when the action's callable threw and
 * `safelyRun` caught it; in that case `result` carries the
 * sanitised "failed unexpectedly" `ActionResult::failure()` that
 * `safelyRun` synthesised before producing this event.
 *
 * `durationMs` is wall-clock time around the callable invocation —
 * exclusive of CSRF / permission / record-loading. Useful for SLO
 * tracking of "the action itself" without setup overhead skewing
 * the percentile.
 *
 * The audit subscriber (`polysource/audit`) is the canonical
 * consumer (cf. ADR-020 §5).
 */
final class ActionExecutedEvent
{
    /**
     * @param list<string> $recordIds — empty list for global actions
     */
    public function __construct(
        public readonly ActionInterface $action,
        public readonly ResourceInterface $resource,
        public readonly array $recordIds,
        public readonly Request $request,
        public readonly ActionResult $result,
        public readonly int $durationMs,
        public readonly ?Throwable $exception = null,
    ) {
    }
}
