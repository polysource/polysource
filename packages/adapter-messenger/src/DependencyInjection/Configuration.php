<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Polysource Messenger adapter config tree.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('polysource_messenger');
        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('failed_transport_name')
                    ->defaultValue('failed')
                    ->cannotBeEmpty()
                    ->info('Name of the Messenger transport that stores failed messages.')
                ->end()
                ->scalarNode('resource_slug')
                    ->defaultValue('failed-messages')
                    ->cannotBeEmpty()
                    ->info('URL slug for the failed-messages resource.')
                ->end()
                ->integerNode('payload_max_bytes')
                    ->defaultValue(50_000)
                    ->min(1)
                    ->info('Maximum serialized payload size before truncation.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
