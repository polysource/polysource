<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Compiler;

use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the names of every filter declared by services tagged
 * `polysource.filter.mapper`, `polysource.filter.formatter`, or
 * `polysource.filter.renderer`, and patches the 3 registries'
 * constructors to receive that name list.
 *
 * The 3 tags are scanned together because not every host declares
 * the full triplet — a chips-only host (e.g. our standalone demo)
 * registers only formatters and would have an empty registry if we
 * only looked at mappers. Forcing dummy mappers/renderers just to
 * seed the registry is a leak of architectural detail.
 *
 * Each tagged service may declare its `name` either via:
 * - the `name` attribute on the tag itself
 *   (`tags: [{ name: polysource.filter.mapper, name: text }]`), or
 * - by defining a public string constant `NAME` on the class.
 *
 * The first option is preferred for clarity; the second is a fallback
 * when the service is auto-configured (no explicit tag attribute).
 *
 * Hosts that don't expose a name via either mechanism rely on the
 * runtime-only `supports()` probing performed by the registry when
 * the formatter is asked to handle a name — so a chips-only host
 * with autoconfigure-tagged formatters and no NAME constant still
 * works as long as the criterion's filter name is supplied at the
 * call site (which it always is, via FilterDefinition::$name).
 */
final class PipelineCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $names = $this->collectKnownNames($container);

        // Patch 2nd ctor arg (knownNames) of each registry.
        foreach ([MapperRegistry::class, FormatterRegistry::class, RendererRegistry::class] as $registry) {
            if ($container->hasDefinition($registry)) {
                $container->getDefinition($registry)->replaceArgument(1, $names);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function collectKnownNames(ContainerBuilder $container): array
    {
        $names = [];
        $pipelineTags = [
            'polysource.filter.mapper',
            'polysource.filter.formatter',
            'polysource.filter.renderer',
        ];

        foreach ($pipelineTags as $tagName) {
            foreach ($container->findTaggedServiceIds($tagName) as $serviceId => $tags) {
                foreach ($tags as $tag) {
                    if (\is_array($tag) && isset($tag['name']) && \is_string($tag['name']) && '' !== $tag['name']) {
                        $names[$tag['name']] = true;
                    }
                }
                // Fallback: read NAME class constant when the tag carries no `name` attr.
                $definition = $container->getDefinition($serviceId);
                $class = $definition->getClass();
                if (\is_string($class) && \defined($class . '::NAME')) {
                    $constant = \constant($class . '::NAME');
                    if (\is_string($constant) && '' !== $constant) {
                        $names[$constant] = true;
                    }
                }
            }
        }

        return array_keys($names);
    }
}
