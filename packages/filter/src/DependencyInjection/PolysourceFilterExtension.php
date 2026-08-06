<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection;

use Polysource\Filter\Form\Type\FilterCollectionType;
use Polysource\Filter\Pipeline\FilterFormatterInterface;
use Polysource\Filter\Pipeline\FilterMapperInterface;
use Polysource\Filter\Pipeline\FilterRendererInterface;
use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Polysource\Filter\Service\FilterService;
use Polysource\Filter\Twig\Extension\FilterTagsExtension;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DI extension for `polysource/filter`.
 *
 * Registers:
 * - `FilterService` (RequestStack-backed session persistence).
 * - The 3 pipeline registries (`MapperRegistry`, `FormatterRegistry`,
 *   `RendererRegistry`), each receiving a tagged_iterator of their
 *   respective interface implementations + the list of known filter
 *   names (filled at compile-time by `PipelineCompilerPass`).
 *
 * Auto-config wires the 3 pipeline interfaces — host services
 * implementing them get the right tag automatically without manual
 * tagging in services.yaml.
 *
 * The known-names list is populated by the compiler pass; here the
 * registries are seeded with an empty list and patched at compile.
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
        // Auto-configure tag for each pipeline phase so host services
        // implementing the interface are picked up without explicit
        // tagging in services.yaml / configurator.
        $container
            ->registerForAutoconfiguration(FilterMapperInterface::class)
            ->addTag('polysource.filter.mapper')
        ;
        $container
            ->registerForAutoconfiguration(FilterFormatterInterface::class)
            ->addTag('polysource.filter.formatter')
        ;
        $container
            ->registerForAutoconfiguration(FilterRendererInterface::class)
            ->addTag('polysource.filter.renderer')
        ;

        // FilterService — agnostic of EasyAdmin / any host.
        $container
            ->register(FilterService::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // The 3 pipeline registries — second constructor arg (known
        // names) is patched in the compiler pass.
        $container
            ->register(MapperRegistry::class)
            ->setArguments([new TaggedIteratorArgument('polysource.filter.mapper'), []])
            ->setPublic(true)
        ;
        $container
            ->register(FormatterRegistry::class)
            ->setArguments([new TaggedIteratorArgument('polysource.filter.formatter'), []])
            ->setPublic(true)
        ;
        $container
            ->register(RendererRegistry::class)
            ->setArguments([new TaggedIteratorArgument('polysource.filter.renderer'), []])
            ->setPublic(true)
        ;

        // FilterCollectionType — Symfony Form auto-discovers FormType
        // services tagged `form.type` via the AbstractType
        // auto-configuration (which is enabled by default in
        // FrameworkBundle). The class extends AbstractType so
        // declaring it autowired is enough; FrameworkBundle's
        // registerForAutoconfiguration tags it on top.
        $container
            ->register(FilterCollectionType::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true)
        ;

        // FilterTagsExtension — Twig function `filter_tags()`. Register
        // it explicitly + tag `twig.extension` because the bundle ships
        // its Twig extension (host apps can't autowire foreign-bundle
        // services, and we don't ship a services.xml).
        //
        // Guarded on TwigBundle presence so non-Twig hosts (rare, but
        // possible: a console-only app, a unit test kernel) don't get
        // an unused service that fails to autowire `Twig\Environment`.
        // The `kernel.bundles` param check uses `hasParameter()` first
        // because some test container builders don't seed it.
        $bundles = $container->hasParameter('kernel.bundles')
            ? $container->getParameter('kernel.bundles')
            : [];
        if (FeatureGate::hasTwigBundle($bundles)) {
            $container
                ->register(FilterTagsExtension::class)
                ->setAutowired(true)
                ->addTag('twig.extension')
            ;
        }

        // SavedView wiring (cf. ADR-019). Requires DoctrineBundle
        // (storage) + SecurityBundle (voter). Hosts missing either
        // get no SavedView services — Twig function returns empty
        // markup, controller route absent.
        $hasStorage = false;

        if (
            interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::savedViewsAvailable($bundles)
        ) {
            $container
                ->register(\Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\SavedView\Storage\SavedViewStorageInterface::class,
                \Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage::class,
            );
            $hasStorage = true;
        }

        if ($hasStorage) {
            $container
                ->register(\Polysource\Filter\SavedView\SavedViewService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;

            // SavedViewApplyService — shared core for the two listeners
            // (`SavedViewApplySubscriber` in EA bridge, `PolysourceSavedViewApplyListener`
            // in the standalone admin) that translate `?view=<id>` into
            // a filtered URL redirect. Extracted in v0.10.0 per audit
            // task #66 (MEDIUM DRY).
            $container
                ->register(\Polysource\Filter\SavedView\SavedViewApplyService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;

            $container
                ->register(\Polysource\Filter\SavedView\Security\SavedViewVoter::class)
                ->setAutowired(true)
                ->addTag('security.voter')
            ;

            // ClearSavedViewListener — consumes `?clear-view=1` to wipe
            // the session-remembered last-used view + redirect to a
            // clean URL. Without it the dropdown's "Clear current
            // view" item only strips the URL query, leaving the
            // session entry dangling so `defaultFor()` resurrects
            // the view as `current` on the next render.
            $container
                ->register(\Polysource\Filter\SavedView\EventListener\ClearSavedViewListener::class)
                ->setAutowired(true)
                ->addTag('kernel.event_subscriber')
            ;
        }

        // SavedViewExtension — Twig function `saved_views_dropdown()`.
        // Registered UNCONDITIONALLY when TwigBundle is loaded, with
        // the SavedView service stack passed as nullable (cf. the
        // extension's constructor docblock). When storage isn't
        // wired the function returns an empty string — templates
        // that call it unconditionally still parse on bridge-alone
        // installs, which is the v0.1.4 architectural fix for the
        // v0.1.1 install-time crash.
        if (FeatureGate::hasTwigBundle($bundles)) {
            $extensionDef = $container
                ->register(\Polysource\Filter\SavedView\Twig\SavedViewExtension::class)
                ->addTag('twig.extension')
            ;
            if ($hasStorage) {
                // Autowire the full deps (SavedViewService, Twig
                // Environment, optional Router + TeamResolver).
                $extensionDef->setAutowired(true);
            } else {
                // Construct with all-null deps so the function is
                // registered but returns empty when called.
                // setAutowired stays false so missing services
                // don't fail DI compilation.
                // v0.9.0 PR 1: 5th arg is the optional CsrfTokenManager;
                // `polysource_csrf_token()` returns '' when null.
                $extensionDef->setArguments([null, null, null, null, null]);
            }
        }

        // ColumnPreference wiring (v0.3.0, parallel to SavedView).
        //
        // Gated on the same DoctrineBundle + SecurityBundle pair: the
        // service needs an EntityManager to persist + a TokenStorage
        // to resolve the current user.
        if (
            interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
            && FeatureGate::hasSecurityBundle($bundles)
        ) {
            $container
                ->register(\Polysource\Filter\ColumnPreference\Storage\DoctrineColumnPreferenceStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\ColumnPreference\Storage\ColumnPreferenceStorageInterface::class,
                \Polysource\Filter\ColumnPreference\Storage\DoctrineColumnPreferenceStorage::class,
            );
            $container
                ->register(\Polysource\Filter\ColumnPreference\ColumnPreferenceService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;
        }

        // BulkActionHistory wiring (v0.5.0).
        // Same gating as ColumnPreference: needs Doctrine + Security.
        if (
            interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
            && FeatureGate::hasSecurityBundle($bundles)
        ) {
            $container
                ->register(\Polysource\Filter\BulkActionHistory\Storage\DoctrineBulkActionHistoryStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\BulkActionHistory\Storage\BulkActionHistoryStorageInterface::class,
                \Polysource\Filter\BulkActionHistory\Storage\DoctrineBulkActionHistoryStorage::class,
            );
            $container
                ->register(\Polysource\Filter\BulkActionHistory\BulkActionHistoryService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;

            // Periodic purge command (v0.6.1) — opt-in via cron in
            // non-regulated hosts. Compliance hosts MUST NOT run it
            // (cf. docblock).
            $container
                ->register(\Polysource\Filter\BulkActionHistory\Command\PurgeBulkActionHistoryCommand::class)
                ->setAutowired(true)
                ->addTag('console.command')
            ;
        }

        // RecentRecords wiring (v0.5.0).
        if (
            interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
            && FeatureGate::hasSecurityBundle($bundles)
        ) {
            $container
                ->register(\Polysource\Filter\RecentRecords\Storage\DoctrineRecentRecordsStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\RecentRecords\Storage\RecentRecordsStorageInterface::class,
                \Polysource\Filter\RecentRecords\Storage\DoctrineRecentRecordsStorage::class,
            );
            $container
                ->register(\Polysource\Filter\RecentRecords\RecentRecordsService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;
        }

        // FilterUrlToken wiring (v0.5.0). Doctrine-only — no
        // Security dependency: tokens are user-agnostic by design.
        if (
            interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && FeatureGate::hasDoctrineBundle($bundles)
        ) {
            $container
                ->register(\Polysource\Filter\FilterUrlToken\Storage\DoctrineFilterUrlTokenStorage::class)
                ->setAutowired(true)
            ;
            $container->setAlias(
                \Polysource\Filter\FilterUrlToken\Storage\FilterUrlTokenStorageInterface::class,
                \Polysource\Filter\FilterUrlToken\Storage\DoctrineFilterUrlTokenStorage::class,
            );
            $container
                ->register(\Polysource\Filter\FilterUrlToken\FilterUrlTokenService::class)
                ->setAutowired(true)
                ->setPublic(true)
            ;

            // Periodic purge command (v0.6.1) — hosts wire it to a
            // nightly cron, otherwise the polysource_filter_url_tokens
            // table grows unbounded.
            $container
                ->register(\Polysource\Filter\FilterUrlToken\Command\PurgeFilterUrlTokensCommand::class)
                ->setAutowired(true)
                ->addTag('console.command')
            ;
        }

        // ColumnPreferenceExtension — Twig functions
        // `polysource_column_hidden(...)` and `polysource_hidden_columns(...)`.
        // Same nullable-service pattern as SavedViewExtension: the
        // extension is always registered when TwigBundle is loaded so
        // templates parse on bridge-alone installs; the service is
        // null when storage isn't wired, in which case the functions
        // return safe defaults (false / []).
        if (FeatureGate::hasTwigBundle($bundles)) {
            $extensionDef = $container
                ->register(\Polysource\Filter\ColumnPreference\Twig\ColumnPreferenceExtension::class)
                ->addTag('twig.extension')
            ;
            if (
                interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
                && FeatureGate::hasDoctrineBundle($bundles)
                && FeatureGate::hasSecurityBundle($bundles)
            ) {
                $extensionDef->setAutowired(true);
            } else {
                $extensionDef->setArguments([null]);
            }
        }
    }

    public function getAlias(): string
    {
        return 'polysource_filter';
    }
}
