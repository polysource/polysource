<?php

declare(strict_types=1);

namespace Polysource\Bundle\DependencyInjection;

use Composer\InstalledVersions;
use OutOfBoundsException;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Resource\ResourceInterface;
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
        if (null !== $themePath) {
            $container->prependExtensionConfig('twig', [
                'paths' => [$themePath => 'Polysource'],
            ]);
        }

        // Resolve the layout template from user config (if set) and
        // expose it as a Twig global so the bundled index/detail
        // templates can do `{% extends polysource_layout %}`. Hosts
        // override the chrome (e.g. wrap pages in EA's sidebar) via
        // `polysource.layout_template: @Acme/admin/layout.html.twig`
        // — one config line, no need to copy every template.
        $userConfigs = $container->getExtensionConfig('polysource');
        $layoutTemplate = '@Polysource/layout.html.twig';
        foreach ($userConfigs as $userConfig) {
            if (\is_array($userConfig) && isset($userConfig['layout_template']) && \is_string($userConfig['layout_template']) && '' !== $userConfig['layout_template']) {
                $layoutTemplate = $userConfig['layout_template'];
            }
        }
        // Escape leading `@` so Symfony DI doesn't read it as a service
        // reference (Twig globals are processed as DI parameter values
        // and `@Foo/bar` would otherwise resolve to a service called
        // `Foo/bar`). Double-`@` is the canonical literal escape.
        $literal = str_starts_with($layoutTemplate, '@') ? '@' . $layoutTemplate : $layoutTemplate;
        $container->prependExtensionConfig('twig', [
            'globals' => ['polysource_layout' => $literal],
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

        $layoutTemplate = $config['layout_template'] ?? '@Polysource/layout.html.twig';
        \assert(\is_string($layoutTemplate));
        $container->setParameter('polysource.layout_template', $layoutTemplate);

        $container->registerForAutoconfiguration(DataSourceInterface::class)
            ->addTag('polysource.data_source')
        ;

        // Auto-tag the remaining Polysource interfaces so adapter
        // authors don't repeat the boilerplate. ResourceInterface stays
        // attribute-driven (cf. ADR-005 — explicit `#[AsResource]` is
        // the recommended discovery path) but actions / filters that
        // implement the contract get tagged automatically.
        $container->registerForAutoconfiguration(ActionInterface::class)
            ->addTag('polysource.action')
        ;
        $container->registerForAutoconfiguration(FilterInterface::class)
            ->addTag('polysource.filter')
        ;
        $container->registerForAutoconfiguration(ResourceInterface::class)
            ->addTag('polysource.resource')
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
