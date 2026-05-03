<?php

declare(strict_types=1);

namespace Polysource\Filter;

use Polysource\Filter\DependencyInjection\Compiler\PipelineCompilerPass;
use Polysource\Filter\DependencyInjection\PolysourceFilterExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony bundle entry point for `polysource/filter`.
 *
 * Wires the `PipelineCompilerPass` so services tagged
 * `polysource.filter.{mapper,formatter,renderer}` are collected into
 * the three registries at compile-time.
 *
 * No Twig/Form dependency declared at the Bundle level — those are
 * required via composer.json and consumed by individual services.
 */
final class PolysourceFilterBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new PipelineCompilerPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceFilterExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
