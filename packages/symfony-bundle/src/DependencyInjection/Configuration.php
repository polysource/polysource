<?php

declare(strict_types=1);

namespace Polysource\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Polysource bundle config tree.
 *
 * Cf. ADR-003 — `url_prefix` is the only configurable knob in v0.1.
 * Resources are NOT declared here (cf. ADR-005 — interface methods + `#[AsResource]`).
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('polysource');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('url_prefix')
                    ->defaultValue('/admin')
                    ->cannotBeEmpty()
                    ->info('URL prefix under which all Polysource routes are mounted.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
