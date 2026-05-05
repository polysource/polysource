<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Service;

use Polysource\WorkflowBridge\Resource\WorkflowAwareInterface;
use Symfony\Component\Workflow\Transition;

/**
 * Enumerates the transitions currently activable on a record by
 * delegating to Symfony Workflow's `getEnabledTransitions()` —
 * which already runs guards (`workflow.<name>.guard.<transition>`
 * events). We do NOT re-implement the guard logic.
 *
 * Returns an empty list when the resource has no resolvable
 * Workflow (multi-tenant config drift, unsupported subject) — the
 * caller treats that as "no transition actions to render", which
 * is exactly what the UX wants.
 */
final class TransitionDiscovery
{
    public function __construct(private readonly WorkflowResolver $resolver)
    {
    }

    /**
     * @return list<Transition>
     */
    public function enabledFor(WorkflowAwareInterface $resource, ?object $subject): array
    {
        $workflow = $this->resolver->resolve($resource, $subject);
        if (null === $workflow || null === $subject) {
            return [];
        }

        $transitions = [];
        foreach ($workflow->getEnabledTransitions($subject) as $transition) {
            $transitions[] = $transition;
        }

        return $transitions;
    }
}
