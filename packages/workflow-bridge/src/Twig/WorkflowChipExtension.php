<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing the workflow state-chip helpers used by
 * `_state_chip.html.twig` and host templates.
 *
 * Functions:
 *  - `polysource_workflow_chip_palette(workflow, state)` →
 *    Bootstrap palette slug ("success", "danger", "secondary", …)
 *  - `polysource_workflow_state_label(state)` → display label;
 *    we just title-case the state by default. Hosts override via
 *    Symfony Translator (the chip template wraps it in `|trans`).
 */
final class WorkflowChipExtension extends AbstractExtension
{
    public function __construct(private readonly WorkflowChipPalette $palette)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_workflow_chip_palette', $this->palette->paletteFor(...)),
            new TwigFunction('polysource_workflow_state_label', $this->stateLabel(...)),
        ];
    }

    public function stateLabel(string $state): string
    {
        // Default humanisation: snake_case → "Snake case". Hosts that
        // localise wrap the result in |trans on the template side.
        return ucfirst(str_replace(['_', '-'], ' ', $state));
    }
}
