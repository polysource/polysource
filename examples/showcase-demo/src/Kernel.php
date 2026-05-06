<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function build(ContainerBuilder $container): void
    {
        // Override the @Polysource Twig namespace BEFORE the bundle's
        // own path. Bundle prepend runs first, putting its template
        // dir at the head of the chain — host paths added via
        // twig.yaml come AFTER, so they lose. Calling prependPath()
        // on the loader inserts our directory at index 0, winning
        // every lookup. Used here for templates/polysource/_navigation.html.twig.
        $container->addCompilerPass(new class($this->getProjectDir()) implements CompilerPassInterface {
            public function __construct(private readonly string $projectDir)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                if (!$container->hasDefinition('twig.loader.native_filesystem')) {
                    return;
                }
                $loader = $container->getDefinition('twig.loader.native_filesystem');
                $loader->addMethodCall('prependPath', [
                    $this->projectDir.'/templates/polysource',
                    'Polysource',
                ]);
            }
        });
    }
}
