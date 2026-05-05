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
     * Without these prepends, the controllers declared in
     * `assets/package.json` (`polysource--filter-chips`,
     * `polysource--filter-subpanel`) are invisible to host apps —
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
            \array_key_exists('FrameworkBundle', $bundles)
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
        if (\is_array($bundles) && \array_key_exists('TwigBundle', $bundles)) {
            $container
                ->register(FilterTagsExtension::class)
                ->setAutowired(true)
                ->addTag('twig.extension')
            ;
        }

        // SavedView wiring (cf. ADR-019).
        //
        // DoctrineSavedViewStorage is the default storage — gated by
        // `class_exists(EntityManagerInterface)`. Hosts without
        // Doctrine wire their own SavedViewStorageInterface service
        // manually (see docs/user/filter/saved-views.md).
        //
        // SavedViewService + SavedViewExtension + SavedViewVoter are
        // only registered when a storage alias exists, to avoid a
        // DI-compilation crash for hosts that haven't wired storage
        // yet.
        $hasStorage = false;
        if (class_exists(\Doctrine\ORM\EntityManagerInterface::class)) {
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

            $container
                ->register(\Polysource\Filter\SavedView\Security\SavedViewVoter::class)
                ->setAutowired(true)
                ->addTag('security.voter')
            ;

            // SavedViewExtension — Twig function `saved_views_dropdown()`.
            // Requires both Twig (for the function) and the SavedView
            // service stack (for the data).
            if (\is_array($bundles) && \array_key_exists('TwigBundle', $bundles)) {
                $container
                    ->register(\Polysource\Filter\SavedView\Twig\SavedViewExtension::class)
                    ->setAutowired(true)
                    ->addTag('twig.extension')
                ;
            }
        }
    }

    public function getAlias(): string
    {
        return 'polysource_filter';
    }
}
