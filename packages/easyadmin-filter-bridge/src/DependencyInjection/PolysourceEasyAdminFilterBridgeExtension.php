<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection;

use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ChipLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ColumnPreferenceLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\EnhancerLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ExportLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\FilterTreeLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\FilterUrlTokenLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\ListingUxLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\RowDetailLoader;
use Polysource\EasyAdminFilterBridge\DependencyInjection\Loader\SavedViewControllerLoader;
use Polysource\Filter\DependencyInjection\FeatureGate;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DI extension for `polysource/easyadmin-filter-bridge` — a table of
 * contents over per-feature loaders (ADR-0032): enhancers + form
 * types, chips, the listing-UX Twig helpers, the filter-tree
 * machinery, then the optional endpoint features (saved views,
 * export, filter URL tokens, column preferences). Each loader
 * carries its own gate and the rationale comments for its wiring.
 *
 * Two gates stay extension-level because they're transverse:
 * the bundle config (`auto_register_routes`, consumed by
 * `Bundle::boot()`), and the C1 EasyAdmin guard — the bridge is a
 * no-op on EA-less kernels in multi-kernel apps (surfaced
 * 2026-05-14 by dogfooding round 3).
 */
final class PolysourceEasyAdminFilterBridgeExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Auto-register our form theme into the host app's `twig.form_themes`
     * config. Means a host that does `composer require
     * polysource/easyadmin-filter-bridge` immediately gets the rendered
     * preset buttons / chips / data-attributes — no Twig config to write.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!FeatureGate::hasTwigBundle($bundles)) {
            return;
        }

        // Multi-kernel apps register the bridge globally but only
        // load EasyAdminBundle on one kernel. Splicing our views
        // into `@EasyAdmin` on a kernel where EA isn't loaded poisons
        // the @EasyAdmin namespace with templates that reference
        // EA-only Twig globals (`ea`, …) and crashes lint:twig /
        // cache:warmup. Short-circuit when EA is absent.
        // Surfaced 2026-05-14 by dogfooding round 3 (friction C1).
        if (!FeatureGate::hasEasyAdminBundle($bundles)) {
            return;
        }

        // 1) Register our form theme so the enhanced widgets render
        //    without any host-side Twig config.
        $container->prependExtensionConfig('twig', [
            'form_themes' => [
                '@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig',
            ],
        ]);

        // 2) Splice our `Resources/views/` into the `@EasyAdmin`
        //    Twig namespace at higher priority so that
        //    `Resources/views/crud/index.html.twig` is picked up
        //    instead of the upstream one. The override extends
        //    `@!EasyAdmin/crud/index.html.twig` to fall back to
        //    the original within Twig — no infinite loop.
        $bridgeViewsDir = \dirname(__DIR__, 2) . '/Resources/views';
        $container->prependExtensionConfig('twig', [
            'paths' => [
                $bridgeViewsDir => 'EasyAdmin',
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        // Process bundle config (currently: `auto_register_routes`).
        // Stored as a container parameter so Bundle::boot() can read
        // it without having to re-parse the config tree.
        $config = $this->processConfiguration(new Configuration(), $configs);
        $container->setParameter(
            'polysource_easyadmin_filter_bridge.auto_register_routes',
            (bool) ($config['auto_register_routes'] ?? true),
        );

        // C1 guard — bridge bootstraps no services on EA-less kernels.
        // Hosts that register the bundle globally (the Flex recipe
        // default) on a multi-kernel app would otherwise see DI
        // autowire fail when non-EA kernels try to compile
        // `ChipValueFormatter` (type-hints EA's
        // `AdminContextProviderInterface`). With this guard the
        // bundle is harmless to load everywhere; services only
        // appear where they can actually wire.
        // Surfaced 2026-05-14 by dogfooding round 3.
        $bundles = $container->hasParameter('kernel.bundles')
            ? $container->getParameter('kernel.bundles')
            : [];
        if (!FeatureGate::hasEasyAdminBundle($bundles)) {
            return;
        }

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
            new EnhancerLoader(),
            new ChipLoader(),
            new ListingUxLoader(),
            new FilterTreeLoader(),
            new SavedViewControllerLoader(),
            new ExportLoader(),
            new FilterUrlTokenLoader(),
            new ColumnPreferenceLoader(),
            new RowDetailLoader(),
        ];
    }

    public function getAlias(): string
    {
        return 'polysource_easyadmin_filter_bridge';
    }
}
