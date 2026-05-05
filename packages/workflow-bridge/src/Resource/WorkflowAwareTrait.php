<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Resource;

/**
 * Default implementation of {@see WorkflowAwareInterface}. Mix this
 * trait into a Polysource resource that already extends
 * `AbstractResource` and override only the methods you need.
 */
trait WorkflowAwareTrait
{
    public function getWorkflowName(): ?string
    {
        return null;
    }

    public function getStatePropertyName(): string
    {
        return 'state';
    }
}
