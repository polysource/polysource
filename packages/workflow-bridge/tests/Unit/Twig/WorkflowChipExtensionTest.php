<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Polysource\WorkflowBridge\Twig\WorkflowChipExtension;
use Polysource\WorkflowBridge\Twig\WorkflowChipPalette;

final class WorkflowChipExtensionTest extends TestCase
{
    public function testRegistersExpectedFunctions(): void
    {
        $ext = new WorkflowChipExtension(new WorkflowChipPalette());

        $names = array_map(
            static fn ($f) => $f->getName(),
            $ext->getFunctions(),
        );
        sort($names);

        self::assertSame(
            ['polysource_workflow_chip_palette', 'polysource_workflow_state_label'],
            $names,
        );
    }

    public function testStateLabelHumanisesSnakeAndKebabCase(): void
    {
        $ext = new WorkflowChipExtension(new WorkflowChipPalette());

        self::assertSame('Mark as paid', $ext->stateLabel('mark_as_paid'));
        self::assertSame('In review', $ext->stateLabel('in-review'));
        self::assertSame('Draft', $ext->stateLabel('draft'));
    }

    public function testPaletteFallsBackThroughExactStateThenWildcardThenDefault(): void
    {
        $palette = new WorkflowChipPalette([
            'order' => [
                'paid' => 'success',
                'cancelled' => 'danger',
                '*' => 'info', // wildcard fallback for any other state
            ],
        ]);
        $ext = new WorkflowChipExtension($palette);

        $functions = $ext->getFunctions();
        $paletteFn = $functions[0]->getCallable();
        self::assertIsCallable($paletteFn);

        // Exact match
        self::assertSame('success', $paletteFn('order', 'paid'));
        // Wildcard fallback
        self::assertSame('info', $paletteFn('order', 'never-defined'));
        // Unknown workflow → default
        self::assertSame('secondary', $paletteFn('unknown-workflow', 'whatever'));
    }
}
