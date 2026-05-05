<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Resource;

/**
 * Marker + metadata interface that Polysource resources implement to
 * opt into the Symfony Workflow bridge.
 *
 * Implementing this interface tells Polysource:
 *  1. Generate inline transition actions automatically (via
 *     {@see \Polysource\WorkflowBridge\Action\TransitionActionFactory}).
 *  2. Render the current state as a Bootstrap chip via
 *     `workflow_state_chip(record)` Twig.
 *  3. Trace transitions in the audit log (ADR-020) — implicit, no
 *     extra wiring.
 *
 * Opt-in by design (cf. ADR-021 §2): the bundle does NOT inspect
 * every resource looking for a workflow. A resource is silently
 * ignored if it doesn't implement this contract.
 */
interface WorkflowAwareInterface
{
    /**
     * The Symfony Workflow / state machine name as registered under
     * `framework.workflows.<name>`. Returns `null` when the workflow
     * cannot be determined at config time — multi-tenant apps that
     * pick the workflow per record fall back to runtime resolution
     * via {@see \Symfony\Component\Workflow\Registry::get()}.
     */
    public function getWorkflowName(): ?string;

    /**
     * Property on the underlying domain object holding the current
     * place. Default convention: `state`. Hosts override for legacy
     * schemas (`status`, `current_step`, `phase`, …).
     *
     * Used purely by the Twig state-chip helper. Symfony Workflow's
     * marking-store API is the source of truth for transitions; this
     * value is just the cosmetic field rendered in the index.
     */
    public function getStatePropertyName(): string;
}
