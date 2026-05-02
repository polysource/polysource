<?php

declare(strict_types=1);

namespace Polysource\Bundle\DependencyInjection;

use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\DataSource\DataSourceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads Polysource services and wires the autoconfiguration tags.
 *
 * Cf. the project context file §"Symfony DI tags" for the canonical tag names.
 */
final class PolysourceExtension extends Extension
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(\dirname(__DIR__) . '/Resources/config'),
        );
        $loader->load('services.php');

        $urlPrefix = $config['url_prefix'] ?? '/admin';
        \assert(\is_string($urlPrefix));
        $container->setParameter(
            'polysource.url_prefix',
            '/' . trim($urlPrefix, '/'),
        );

        $container->registerForAutoconfiguration(DataSourceInterface::class)
            ->addTag('polysource.data_source')
        ;

        $container->registerAttributeForAutoconfiguration(
            AsResource::class,
            static function (ChildDefinition $definition, AsResource $attribute): void {
                $tagAttributes = [];
                if (null !== $attribute->name) {
                    $tagAttributes['name'] = $attribute->name;
                }
                $definition->addTag('polysource.resource', $tagAttributes);
            },
        );
    }

    public function getAlias(): string
    {
        return 'polysource';
    }
}
