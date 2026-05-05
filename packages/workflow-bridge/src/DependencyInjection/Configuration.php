<?php

declare(strict_types=1);

namespace Polysource\WorkflowBridge\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration tree for `polysource/workflow-bridge`.
 *
 * Hosts wire palettes per workflow:
 *
 *     polysource_workflow_bridge:
 *         palettes:
 *             order:
 *                 draft:     secondary
 *                 paid:      success
 *                 cancelled: danger
 *                 '*':       info  # wildcard fallback
 *
 * `*` is treated as a per-workflow wildcard fallback by
 * {@see \Polysource\WorkflowBridge\Twig\WorkflowChipPalette}.
 * When a state has no exact mapping AND no wildcard, the chip
 * falls back to `secondary` (Bootstrap neutral default).
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('polysource_workflow_bridge');

        $tree->getRootNode()
            ->children()
                ->arrayNode('palettes')
                    ->info('Per-workflow Bootstrap palette mapping for state chips.')
                    ->useAttributeAsKey('workflow')
                    ->arrayPrototype()
                        ->useAttributeAsKey('state')
                        ->scalarPrototype()
                            ->validate()
                                ->ifTrue(static fn ($v) => !\is_string($v) || '' === $v)
                                ->thenInvalid('Palette slug must be a non-empty string (Bootstrap class without the "text-bg-" prefix, e.g. "success").')
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tree;
    }
}
