<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Tests\Unit\Action;

use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\DataRecord;
use Polysource\WorkflowBridge\Action\ApplyTransitionAction;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Workflow;

/**
 * Pin the action contract:
 *  - name = transition-<transition_name>
 *  - permission = POLYSOURCE_WORKFLOW_TRANSITION_<UPPER>
 *  - applies the transition on success, mutating the subject's
 *    `state` property
 *  - converts TransitionException → ActionResult::failure (graceful)
 *  - rejects records without a domain object on rawSource
 */
final class ApplyTransitionActionTest extends TestCase
{
    public function testNameIncludesTransitionName(): void
    {
        $action = $this->makeAction('pay');
        self::assertSame('transition-pay', $action->getName());
    }

    public function testLabelIsHumanised(): void
    {
        $action = $this->makeAction('mark_as_paid');
        self::assertSame('Mark as paid', $action->getLabel());
    }

    public function testPermissionIsScopedPerTransition(): void
    {
        $action = $this->makeAction('cancel');
        self::assertSame('POLYSOURCE_WORKFLOW_TRANSITION_CANCEL', $action->getPermission());
    }

    public function testIsDisplayedOnlyWhenWorkflowAllowsTransition(): void
    {
        $action = $this->makeAction('pay');
        $order = new OrderRecord();
        // 'pay' goes draft → paid. Initial state is 'draft' so it
        // should be enabled.
        self::assertTrue($action->isDisplayed(['subject' => $order]));

        $order->state = 'paid'; // already paid → 'pay' no longer activable
        self::assertFalse($action->isDisplayed(['subject' => $order]));
    }

    public function testIsDisplayedFalseWithoutSubject(): void
    {
        $action = $this->makeAction('pay');
        self::assertFalse($action->isDisplayed([]));
    }

    public function testExecuteSucceedsAndMutatesSubject(): void
    {
        $order = new OrderRecord();
        $action = $this->makeAction('pay');

        $result = $action->execute(new DataRecord('1', ['state' => 'draft'], $order));

        self::assertTrue($result->success);
        self::assertSame('paid', $order->state);
    }

    public function testExecuteReturnsFailureWhenTransitionRejected(): void
    {
        $order = new OrderRecord();
        $order->state = 'paid'; // 'pay' no longer applicable
        $action = $this->makeAction('pay');

        $result = $action->execute(new DataRecord('1', [], $order));

        self::assertFalse($result->success);
        self::assertStringContainsString('rejected', (string) $result->message);
    }

    public function testExecuteReturnsFailureWhenRawSourceMissing(): void
    {
        $action = $this->makeAction('pay');
        $result = $action->execute(new DataRecord('1', [])); // no rawSource

        self::assertFalse($result->success);
        self::assertStringContainsString('rawSource', (string) $result->message);
    }

    private function makeAction(string $transitionName): ApplyTransitionAction
    {
        $workflow = new Workflow(
            $this->definition(),
            new MethodMarkingStore(true, 'state'),
            null,
            'order',
        );
        $transitionMatch = null;
        foreach ($workflow->getDefinition()->getTransitions() as $t) {
            if ($t->getName() === $transitionName) {
                $transitionMatch = $t;
                break;
            }
        }
        if (null === $transitionMatch) {
            $transitionMatch = new Transition($transitionName, 'draft', 'paid');
        }

        return new ApplyTransitionAction($workflow, $transitionMatch);
    }

    private function definition(): Definition
    {
        $b = new DefinitionBuilder();
        $b->addPlace('draft');
        $b->addPlace('paid');
        $b->addPlace('cancelled');
        $b->setInitialPlaces('draft');
        $b->addTransition(new Transition('pay', 'draft', 'paid'));
        $b->addTransition(new Transition('mark_as_paid', 'draft', 'paid'));
        $b->addTransition(new Transition('cancel', 'draft', 'cancelled'));

        return $b->build();
    }
}

final class OrderRecord
{
    public string $state = 'draft';
}
