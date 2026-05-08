<?php

declare(strict_types=1);

namespace Polysource\Bundle\DependencyInjection\Compiler;

use Polysource\Bundle\Plugin\PluginRegistry;
use Polysource\Core\Plugin\AdminPluginInterface;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Wires services tagged `polysource.plugin` into `PluginRegistry`.
 *
 * Two discovery shapes are supported:
 *
 * 1. Explicit tag in services.yaml or via the bundle's own DI extension:
 *    ```yaml
 *    services:
 *      My\Plugin: { tags: [polysource.plugin] }
 *    ```
 * 2. Implicit detection: any service whose class implements
 *    `AdminPluginInterface` is auto-tagged here. This avoids
 *    plugin authors having to register the tag manually when their
 *    Bundle class is already a service candidate (which is the
 *    common case for `#[AsPlugin]` + Symfony bundle classes registered
 *    via `bundles.php` and exposed as services).
 *
 * Per ADR-018 §4.
 *
 * @since 0.1.0
 */
final class PluginCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PluginRegistry::class)) {
            return;
        }

        // 1. Auto-discover registered Symfony bundles whose class
        //    implements AdminPluginInterface, and register each as a
        //    container service tagged `polysource.plugin`. Bundles
        //    aren't services in Symfony — they live in the kernel's
        //    bundle list — so we reach them via `kernel.bundles`
        //    (a class-name → class-name map exposed as a parameter).
        $bundles = $container->hasParameter('kernel.bundles')
            ? $container->getParameter('kernel.bundles')
            : [];
        if (\is_array($bundles)) {
            foreach ($bundles as $bundleClass) {
                if (!\is_string($bundleClass)) {
                    continue;
                }
                try {
                    if (!class_exists($bundleClass)) {
                        continue;
                    }
                } catch (\Throwable) {
                    continue;
                }

                if (!is_subclass_of($bundleClass, AdminPluginInterface::class)) {
                    continue;
                }

                // The DI service IS-A new instance of the bundle.
                // Bundle::__construct() takes no args; the metadata
                // (name, version) is static (reflection on the class
                // attribute), so this fresh instance returns the same
                // values as the kernel's bundle instance.
                if (!$container->hasDefinition($bundleClass)) {
                    $container
                        ->register($bundleClass, $bundleClass)
                        ->setPublic(false)
                        ->setAutowired(false)
                        ->setAutoconfigured(false)
                        ->addTag('polysource.plugin')
                    ;
                } elseif (!$container->getDefinition($bundleClass)->hasTag('polysource.plugin')) {
                    $container->getDefinition($bundleClass)->addTag('polysource.plugin');
                }
            }
        }

        // 2. Also auto-tag any service whose class implements
        //    AdminPluginInterface — covers hosts that explicitly
        //    register a non-Bundle plugin service in services.yaml
        //    without going through autoconfiguration.
        //
        // We must not let `class_exists()` propagate fatal errors when
        // a service in the container has a missing transitive dep
        // (e.g. symfony/translation's TransMethodVisitor depending on
        // nikic/php-parser). Symfony's DebugClassLoader turns these
        // into ClassNotFoundError at autoload time. Catching every
        // Throwable lets compilation proceed: the missing class
        // genuinely isn't a plugin candidate, and the runtime that
        // uses it will fail informatively if the host actually tries.
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();
            if (!\is_string($class)) {
                continue;
            }
            try {
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            if (!is_subclass_of($class, AdminPluginInterface::class)) {
                continue;
            }

            if (!$definition->hasTag('polysource.plugin')) {
                $definition->addTag('polysource.plugin');
            }
        }

        // 3. Wire the discovered services into PluginRegistry.
        $references = [];
        foreach ($container->findTaggedServiceIds('polysource.plugin') as $serviceId => $tags) {
            $references[] = new Reference($serviceId);
        }

        $container
            ->getDefinition(PluginRegistry::class)
            ->setArgument(0, new IteratorArgument($references))
        ;
    }
}
