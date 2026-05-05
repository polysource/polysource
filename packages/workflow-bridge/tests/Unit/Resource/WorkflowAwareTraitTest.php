<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Tests\Unit\Resource;

use PHPUnit\Framework\TestCase;
use Polysource\WorkflowBridge\Resource\WorkflowAwareInterface;
use Polysource\WorkflowBridge\Resource\WorkflowAwareTrait;

final class WorkflowAwareTraitTest extends TestCase
{
    public function testDefaultsAreNullWorkflowNameAndStateProperty(): void
    {
        $resource = new class implements WorkflowAwareInterface {
            use WorkflowAwareTrait;
        };

        self::assertNull($resource->getWorkflowName());
        self::assertSame('state', $resource->getStatePropertyName());
    }

    public function testHostsCanOverrideOnlyWorkflowName(): void
    {
        $resource = new class implements WorkflowAwareInterface {
            use WorkflowAwareTrait;

            /**
             * Overriding the trait's nullable return with a non-null
             * literal is intentional — PHPStan would otherwise flag
             * the trait return as overly wide if every override
             * returned a string.
             *
             * @phpstan-ignore-next-line return.unusedType — interface contract is `?string`; we always know
             */
            public function getWorkflowName(): ?string
            {
                return 'order';
            }
        };

        self::assertSame('order', $resource->getWorkflowName());
        self::assertSame('state', $resource->getStatePropertyName());
    }
}
