<?php

declare(strict_types=1);

namespace Polysource\Bundle\DependencyInjection;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\DataSource\DataSourceInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads Polysource services and wires the autoconfiguration tags.
 *
 * Cf. the project context file §"Symfony DI tags" for the canonical tag names.
 */
final class PolysourceExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('twig')) {
            return;
        }

        $themePath = self::locateTwigThemeTemplates();
        if (null === $themePath) {
            return;
        }

        $container->prependExtensionConfig('twig', [
            'paths' => [$themePath => 'Polysource'],
        ]);
    }

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

        $maxPageSize = $config['max_page_size'] ?? 200;
        \assert(\is_int($maxPageSize));
        $container->setParameter('polysource.max_page_size', $maxPageSize);

        $maxBulkIds = $config['max_bulk_ids'] ?? 500;
        \assert(\is_int($maxBulkIds));
        $container->setParameter('polysource.max_bulk_ids', $maxBulkIds);

        $container->registerForAutoconfiguration(DataSourceInterface::class)
            ->addTag('polysource.data_source')
        ;

        $container->registerAttributeForAutoconfiguration(
            AsResource::class,
            static function (ChildDefinition $definition, AsResource $attribute): void {
                unset($attribute);
                $definition->addTag('polysource.resource');
            },
        );
    }

    public function getAlias(): string
    {
        return 'polysource';
    }

    /**
     * Locate the polysource/twig-theme installed templates directory.
     *
     * Returns null when the package is missing (the bundle still works —
     * the view listener falls back to JSON).
     */
    private static function locateTwigThemeTemplates(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            $packagePath = InstalledVersions::getInstallPath('polysource/twig-theme');
        } catch (OutOfBoundsException) {
            return null;
        }

        if (null === $packagePath) {
            return null;
        }

        $templates = $packagePath . '/templates';

        return is_dir($templates) ? $templates : null;
    }
}
