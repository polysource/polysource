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
                    ->validate()
                        ->ifTrue(static fn ($v): bool => !\is_string($v) || 1 !== preg_match('#^/?[A-Za-z0-9_/-]+$#', $v))
                        ->thenInvalid('polysource.url_prefix must match /^[A-Za-z0-9_/-]+$/ (no path traversal, no spaces).')
                    ->end()
                ->end()
                ->integerNode('max_page_size')
                    ->defaultValue(200)
                    ->min(1)
                    ->info('Maximum number of records per page accepted from the request. Caps `pageSize` query param.')
                ->end()
                ->integerNode('max_bulk_ids')
                    ->defaultValue(500)
                    ->min(1)
                    ->info('Maximum number of record identifiers accepted in a single bulk-action request.')
                ->end()
                ->scalarNode('layout_template')
                    ->defaultValue('@Polysource/layout.html.twig')
                    ->cannotBeEmpty()
                    ->info('Twig template that Polysource pages extend. Override to wrap the index/detail pages in a host-specific chrome (e.g. an EasyAdmin sidebar).')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
