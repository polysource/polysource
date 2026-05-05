<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Action;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Inline action that applies one Symfony Workflow transition to a
 * single record.
 *
 * Auto-generated per-transition by {@see TransitionActionFactory}
 * — hosts don't write these by hand. The action is a pure
 * `InlineActionInterface`, so it goes through the standard
 * `ActionController::safelyRun()` pipeline: CSRF check, permission
 * gate, audit subscriber (ADR-020).
 *
 * Permission attribute: `POLYSOURCE_WORKFLOW_TRANSITION_<UPPER_NAME>`
 * — granular per transition. Hosts that prefer a blanket
 * `POLYSOURCE_WORKFLOW` ship a voter that prefix-matches
 * `POLYSOURCE_WORKFLOW_TRANSITION_*` (the same pattern the
 * messenger-demo uses for `POLYSOURCE_*`).
 *
 * Failure handling:
 *  - `TransitionException` (guard refusal, race condition: the
 *    record's marking changed between discovery and apply) is
 *    converted to `ActionResult::failure()`. The audit log will
 *    record `outcome = failure`, NOT `exception` — the transition
 *    was *rejected*, not crashed.
 *  - Any other Throwable propagates and is caught by `safelyRun`,
 *    recorded as `outcome = exception`.
 */
final class ApplyTransitionAction implements InlineActionInterface
{
    public function __construct(
        private readonly WorkflowInterface $workflow,
        private readonly Transition $transition,
    ) {
    }

    public function getName(): string
    {
        return 'transition-' . $this->transition->getName();
    }

    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->transition->getName()));
    }

    /**
     * @phpstan-ignore-next-line return.unusedType — interface contract is `?string`; we always know
     */
    public function getIcon(): ?string
    {
        return 'arrow-right-circle';
    }

    /**
     * @phpstan-ignore-next-line return.unusedType — interface contract is `?string`; we always know
     */
    public function getPermission(): ?string
    {
        return 'POLYSOURCE_WORKFLOW_TRANSITION_' . strtoupper($this->transition->getName());
    }

    public function isDisplayed(array $context = []): bool
    {
        $subject = $context['subject'] ?? null;
        if (!\is_object($subject)) {
            return false;
        }

        return $this->workflow->can($subject, $this->transition->getName());
    }

    public function execute(DataRecord $record): ActionResult
    {
        $subject = $record->rawSource;
        if (!\is_object($subject)) {
            return ActionResult::failure(\sprintf(
                'Cannot apply transition "%s": record has no domain object on `rawSource`.',
                $this->transition->getName(),
            ));
        }

        try {
            $this->workflow->apply($subject, $this->transition->getName());
        } catch (TransitionException $e) {
            return ActionResult::failure(\sprintf(
                'Transition "%s" rejected: %s',
                $this->transition->getName(),
                $e->getMessage(),
            ));
        }

        return ActionResult::success(\sprintf(
            'Transition "%s" applied.',
            $this->transition->getName(),
        ));
    }
}
