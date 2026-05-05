<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Service;

use Polysource\WorkflowBridge\Resource\WorkflowAwareInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;
use Throwable;

/**
 * Resolves the Symfony Workflow attached to a record, given its
 * declaring resource.
 *
 * Two paths:
 *  1. The resource declares an explicit name via
 *     `WorkflowAwareInterface::getWorkflowName()` → we fetch the
 *     named workflow from the Registry. Strict: if the name is
 *     unknown we return null (the host's workflow config drift).
 *  2. The resource returns null → we ask the Registry which
 *     workflow `supports($subject)` matches. Lets multi-tenant
 *     hosts pick the workflow per-record.
 *
 * Returning null means "no workflow applies to this record" —
 * callers (TransitionDiscovery, ApplyTransitionAction)
 * gracefully treat that as "no transitions available".
 */
final class WorkflowResolver
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function resolve(WorkflowAwareInterface $resource, ?object $subject): ?WorkflowInterface
    {
        if (null === $subject) {
            return null;
        }

        $name = $resource->getWorkflowName();

        try {
            if (null !== $name) {
                return $this->registry->get($subject, $name);
            }

            return $this->registry->get($subject);
        } catch (Throwable) {
            // Registry::get() throws InvalidArgumentException when:
            //  - the named workflow doesn't exist
            //  - no registered workflow `supports($subject)`
            // Both are recoverable: the resource just produces no
            // transition actions and no state chip.
            return null;
        }
    }
}
