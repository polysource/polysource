<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Polysource\WorkflowBridge\Resource\WorkflowAwareInterface;
use Polysource\WorkflowBridge\Service\WorkflowResolver;
use stdClass;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

/**
 * Pin the resolution contract:
 *  - explicit name → fetch by name
 *  - null name → registry::get fallback (supports() match)
 *  - unknown name / no support → null (graceful)
 *  - null subject → null
 */
final class WorkflowResolverTest extends TestCase
{
    private Registry $registry;

    protected function setUp(): void
    {
        $this->registry = new Registry();

        $orderWorkflow = new Workflow(
            $this->orderDefinition(),
            new MethodMarkingStore(true, 'state'),
            null,
            'order',
        );
        $this->registry->addWorkflow(
            $orderWorkflow,
            new InstanceOfSupportStrategy(OrderStub::class),
        );
    }

    public function testResolvesByExplicitName(): void
    {
        $resolver = new WorkflowResolver($this->registry);
        $resource = $this->resourceWith(name: 'order');

        $workflow = $resolver->resolve($resource, new OrderStub());

        self::assertNotNull($workflow);
        self::assertSame('order', $workflow->getName());
    }

    public function testFallsBackToSupportLookupWhenNameIsNull(): void
    {
        $resolver = new WorkflowResolver($this->registry);
        $resource = $this->resourceWith(name: null);

        $workflow = $resolver->resolve($resource, new OrderStub());

        self::assertNotNull($workflow);
        self::assertSame('order', $workflow->getName());
    }

    public function testReturnsNullForUnknownWorkflowName(): void
    {
        $resolver = new WorkflowResolver($this->registry);
        $resource = $this->resourceWith(name: 'does-not-exist');

        self::assertNull($resolver->resolve($resource, new OrderStub()));
    }

    public function testReturnsNullForUnsupportedSubject(): void
    {
        $resolver = new WorkflowResolver($this->registry);
        $resource = $this->resourceWith(name: null);

        self::assertNull($resolver->resolve($resource, new stdClass()));
    }

    public function testReturnsNullForNullSubject(): void
    {
        $resolver = new WorkflowResolver($this->registry);
        $resource = $this->resourceWith(name: 'order');

        self::assertNull($resolver->resolve($resource, null));
    }

    private function resourceWith(?string $name): WorkflowAwareInterface
    {
        return new class($name) implements WorkflowAwareInterface {
            public function __construct(private readonly ?string $name)
            {
            }

            public function getWorkflowName(): ?string
            {
                return $this->name;
            }

            public function getStatePropertyName(): string
            {
                return 'state';
            }
        };
    }

    private function orderDefinition(): Definition
    {
        $builder = new DefinitionBuilder();
        $builder->addPlace('draft');
        $builder->addPlace('paid');
        $builder->addPlace('cancelled');
        $builder->setInitialPlaces('draft');
        $builder->addTransition(new Transition('pay', 'draft', 'paid'));
        $builder->addTransition(new Transition('cancel', 'draft', 'cancelled'));

        return $builder->build();
    }
}

/**
 * Tiny domain stub for the resolver tests. Public `state` property
 * matches the MethodMarkingStore convention (`true, 'state'` =
 * single-state, property-backed).
 */
final class OrderStub
{
    public string $state = 'draft';
}
