<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Loader;

use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Polysource\Filter\Form\Type\FilterCollectionType;
use Polysource\Filter\Pipeline\FilterFormatterInterface;
use Polysource\Filter\Pipeline\FilterMapperInterface;
use Polysource\Filter\Pipeline\FilterRendererInterface;
use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Polysource\Filter\Service\FilterService;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Core filter pipeline — the unconditional heart of the package:
 * FilterService, the 3 pipeline registries, the FilterCollectionType,
 * and the autoconfiguration tags for the 3 pipeline interfaces.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class PipelineLoader implements FeatureLoaderInterface
{
    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
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
    }
}
