<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\Twig;

/**
 * Per-workflow state-chip palette — maps state name → Bootstrap
 * contextual class (`success`, `danger`, `warning`, `info`,
 * `secondary`, `primary`, `light`, `dark`).
 *
 * Hosts wire it via the bundle config:
 *
 *     polysource_workflow_bridge:
 *         palettes:
 *             order:
 *                 draft:     secondary
 *                 paid:      success
 *                 cancelled: danger
 *
 * Lookup falls back, in order:
 *  1. exact `(workflow, state)` mapping
 *  2. wildcard `(workflow, '*')` mapping (host-defined fallback)
 *  3. global `secondary` default — Bootstrap's neutral chip color
 *     looks reasonable on every theme.
 *
 * Stateless / immutable; safe to share across requests.
 */
final class WorkflowChipPalette
{
    public const DEFAULT_PALETTE = 'secondary';

    /**
     * @param array<string, array<string, string>> $palettes nested map: workflow → state → palette slug
     */
    public function __construct(private readonly array $palettes = [])
    {
    }

    public function paletteFor(?string $workflowName, string $state): string
    {
        if (null === $workflowName) {
            return self::DEFAULT_PALETTE;
        }

        $byState = $this->palettes[$workflowName] ?? null;
        if (!\is_array($byState)) {
            return self::DEFAULT_PALETTE;
        }

        if (isset($byState[$state]) && \is_string($byState[$state])) {
            return $byState[$state];
        }

        if (isset($byState['*']) && \is_string($byState['*'])) {
            return $byState['*'];
        }

        return self::DEFAULT_PALETTE;
    }
}
