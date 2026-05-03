<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection\Compiler;

use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects the names of every filter `name` declared by services tagged
 * `polysource.filter.mapper` (canonical source of truth), and patches
 * the 3 registries' constructors to receive that name list.
 *
 * Each tagged service may declare its `name` either via:
 * - the `name` attribute on the tag itself
 *   (`tags: [{ name: polysource.filter.mapper, name: text }]`), or
 * - by defining a public string constant `NAME` on the class.
 *
 * The first option is preferred for clarity; the second is a fallback
 * when the service is auto-configured (no explicit tag attribute).
 *
 * Hosts that need a custom name register a service tagged
 * `polysource.filter.mapper` (and matching formatter/renderer) and
 * provide the name; auto-discovery picks it up at compile time.
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
        foreach ($container->findTaggedServiceIds('polysource.filter.mapper') as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (\is_array($tag) && isset($tag['name']) && \is_string($tag['name']) && '' !== $tag['name']) {
                    $names[$tag['name']] = true;
                }
            }
            // Fallback: read NAME class constant when the tag carries no `name` attr.
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass();
            if (\is_string($class) && \defined($class . '::NAME')) {
                /** @var mixed $constant */
                $constant = \constant($class . '::NAME');
                if (\is_string($constant) && '' !== $constant) {
                    $names[$constant] = true;
                }
            }
        }

        return array_keys($names);
    }
}
