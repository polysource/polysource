<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Action;

use Polysource\WorkflowBridge\Resource\WorkflowAwareInterface;
use Polysource\WorkflowBridge\Service\TransitionDiscovery;
use Polysource\WorkflowBridge\Service\WorkflowResolver;

/**
 * Builds one {@see ApplyTransitionAction} per transition currently
 * activable on a given record.
 *
 * Returns `[]` when the record has no resolvable workflow or no
 * enabled transitions — the resource gets a clean empty list, no
 * exceptions to catch.
 */
final class TransitionActionFactory
{
    public function __construct(
        private readonly WorkflowResolver $resolver,
        private readonly TransitionDiscovery $discovery,
    ) {
    }

    /**
     * @return list<ApplyTransitionAction>
     */
    public function buildFor(WorkflowAwareInterface $resource, ?object $subject): array
    {
        $workflow = $this->resolver->resolve($resource, $subject);
        if (null === $workflow) {
            return [];
        }

        $actions = [];
        foreach ($this->discovery->enabledFor($resource, $subject) as $transition) {
            $actions[] = new ApplyTransitionAction($workflow, $transition);
        }

        return $actions;
    }
}
