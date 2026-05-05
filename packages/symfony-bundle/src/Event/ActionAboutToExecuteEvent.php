<?php

declare(strict_types=1);

namespace Polysource\Bundle\Event;

use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Dispatched immediately *before* an action's callable is invoked
 * inside `ActionController::safelyRun()`.
 *
 * Listeners CAN read the resource / action / recordIds / request
 * but MUST NOT mutate them — Polysource exposes no setter on the
 * event by design (cf. ADR-020). Mutations belong in a custom
 * `ControllerSupport` decorator, not in an event listener.
 *
 * Use cases:
 *  - Audit (we log only `ActionExecutedEvent`, but pre-event lets
 *    hosts emit a "starting" trace / OpenTelemetry span).
 *  - Rate limiting on a per-action basis.
 *  - SLO instrumentation (start timer here, observe in
 *    `ActionExecutedEvent` for end-to-end latency).
 *  - Mercure broadcast of "action in progress" toast.
 *
 * The event is dispatched ONLY when the action's callable is about
 * to be invoked — past CSRF / permission / record-loading checks.
 * If those upstream checks reject the request, no event fires.
 */
final class ActionAboutToExecuteEvent
{
    /**
     * @param list<string> $recordIds — empty list for global actions
     */
    public function __construct(
        public readonly ActionInterface $action,
        public readonly ResourceInterface $resource,
        public readonly array $recordIds,
        public readonly Request $request,
    ) {
    }
}
