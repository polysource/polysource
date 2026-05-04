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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
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
final class PolysourceFilterExtension extends Extension
{
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
    }

    public function getAlias(): string
    {
        return 'polysource_filter';
    }
}
