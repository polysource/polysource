<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle config tree for `polysource/easyadmin-filter-bridge`.
 *
 * One option for now: `auto_register_routes`. Hosts mounting EA
 * under a non-default prefix (typically multi-tenant apps with
 * `/{channel}/admin`) need to skip the bundle's automatic route
 * registration and import the routes themselves under their own
 * prefix. Default `true` preserves zero-config behaviour for the
 * single-tenant `/admin` install — the common case.
 *
 * @since 0.5.7
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('polysource_easyadmin_filter_bridge');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('auto_register_routes')
                    ->defaultTrue()
                    ->info(
                        'Auto-import bridge routes via Bundle::boot(). '
                        . 'Set to false on multi-tenant hosts where EA is '
                        . 'mounted under a custom prefix (e.g. `/{channel}/admin`) '
                        . 'and import `@PolysourceEasyAdminFilterBridge/config/routes.php` '
                        . 'manually from `config/routes.yaml` under your own prefix.'
                    )
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
