<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection;

use Polysource\Filter\DependencyInjection\Loader\BulkActionHistoryLoader;
use Polysource\Filter\DependencyInjection\Loader\ColumnPreferenceLoader;
use Polysource\Filter\DependencyInjection\Loader\FilterTagsLoader;
use Polysource\Filter\DependencyInjection\Loader\FilterUrlTokenLoader;
use Polysource\Filter\DependencyInjection\Loader\PipelineLoader;
use Polysource\Filter\DependencyInjection\Loader\RecentRecordsLoader;
use Polysource\Filter\DependencyInjection\Loader\SavedViewLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DI extension for `polysource/filter` — a table of contents over
 * per-feature loaders (ADR-0032): the always-on filter pipeline,
 * then one loader per optional feature (filter tags, saved views,
 * column preferences, bulk-action history, recent records, filter
 * URL tokens). Each loader carries its own gate and the rationale
 * comments for its wiring.
 *
 * The pipeline registries' known-names list is populated by
 * `PipelineCompilerPass`; the loaders seed them with an empty list
 * patched at compile.
 */
final class PolysourceFilterExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Register the bundle's `assets/` directory as an AssetMapper path
     * AND its `assets/controllers.json` as a StimulusBundle source.
     *
     * Without these prepends, the controller declared in
     * `assets/package.json` (`polysource--filter-chips`) is
     * invisible to host apps —
     * AssetMapper only scans paths it has been told about, and
     * StimulusBundle's auto-discovery only reads
     * `assets/controllers.json` files registered as sources.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!\is_array($bundles)) {
            return;
        }

        // From .../src/DependencyInjection/PolysourceFilterExtension.php
        // up two levels = package root → `/assets`.
        $assetsDir = \dirname(__DIR__, 2) . '/assets';

        // 1) Register `assets/` as a public AssetMapper path so the
        //    `.js` controller files are servable.
        //
        //    AssetMapper ships since Symfony 6.3. On Sf 5.4, and on
        //    Sf 6.x apps that haven't installed `symfony/asset-mapper`,
        //    this prepend crashes container compilation with
        //    "AssetMapper support cannot be enabled as the AssetMapper
        //    component is not installed". Hosts on those stacks use
        //    Webpack Encore (or no JS pipeline at all, like our
        //    standalone demo) — we no-op there. Hosts that do have
        //    AssetMapper continue to receive the prepend.
        if (
            FeatureGate::hasFrameworkBundle($bundles)
            && class_exists(\Symfony\Component\AssetMapper\AssetMapper::class)
        ) {
            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $assetsDir => '@polysource/filter',
                    ],
                ],
            ]);
        }

        // 2) Register the SavedView Doctrine entity namespace so
        //    hosts using DoctrineBundle don't have to add the
        //    mapping by hand. Without this, the
        //    `DoctrineSavedViewStorage` service tries to query
        //    `Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord`
        //    via the EntityManager and Doctrine raises
        //    "class … not found in the chain configured namespaces".
        //
        //    Gated on DoctrineBundle availability — saved views are
        //    optional, hosts without Doctrine never reach this code.
        if (FeatureGate::hasDoctrineBundle($bundles)) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'PolysourceFilterSavedView' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => \dirname(__DIR__) . '/SavedView/Storage/Doctrine',
                            'prefix' => 'Polysource\\Filter\\SavedView\\Storage\\Doctrine',
                            'alias' => 'PolysourceFilterSavedView',
                        ],
                        'PolysourceFilterColumnPreference' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => \dirname(__DIR__) . '/ColumnPreference/Storage/Doctrine',
                            'prefix' => 'Polysource\\Filter\\ColumnPreference\\Storage\\Doctrine',
                            'alias' => 'PolysourceFilterColumnPreference',
                        ],
                        'PolysourceFilterBulkActionHistory' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => \dirname(__DIR__) . '/BulkActionHistory/Storage/Doctrine',
                            'prefix' => 'Polysource\\Filter\\BulkActionHistory\\Storage\\Doctrine',
                            'alias' => 'PolysourceFilterBulkActionHistory',
                        ],
                        'PolysourceFilterRecentRecords' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => \dirname(__DIR__) . '/RecentRecords/Storage/Doctrine',
                            'prefix' => 'Polysource\\Filter\\RecentRecords\\Storage\\Doctrine',
                            'alias' => 'PolysourceFilterRecentRecords',
                        ],
                        'PolysourceFilterFilterUrlToken' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => \dirname(__DIR__) . '/FilterUrlToken/Storage/Doctrine',
                            'prefix' => 'Polysource\\Filter\\FilterUrlToken\\Storage\\Doctrine',
                            'alias' => 'PolysourceFilterFilterUrlToken',
                        ],
                    ],
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // The `kernel.bundles` param check uses `hasParameter()` first
        // because some test container builders don't seed it.
        $bundles = $container->hasParameter('kernel.bundles')
            ? $container->getParameter('kernel.bundles')
            : [];

        // One loader per feature (ADR-0032). Each loader owns its
        // feature's ENTIRE gate in supports() and its wiring — with
        // the "why" comments — in load(). The list is declared here,
        // in reading order, on purpose: no tag-based discovery, DI
        // wiring stays greppable top-to-bottom.
        foreach ($this->featureLoaders() as $loader) {
            if ($loader->supports($bundles)) {
                $loader->load($container, $bundles);
            }
        }
    }

    /**
     * @return list<FeatureLoaderInterface>
     */
    private function featureLoaders(): array
    {
        return [
            new PipelineLoader(),
            new FilterTagsLoader(),
            new SavedViewLoader(),
            new ColumnPreferenceLoader(),
            new BulkActionHistoryLoader(),
            new RecentRecordsLoader(),
            new FilterUrlTokenLoader(),
        ];
    }

    public function getAlias(): string
    {
        return 'polysource_filter';
    }
}
