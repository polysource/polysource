<?php

declare(strict_types=1);

namespace Polysource\Core\Plugin;

use Composer\InstalledVersions;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use ReflectionClass;
use RuntimeException;

/**
 * Default implementation of {@see AdminPluginInterface} that reads
 * `name` + `version` from the {@see AsPlugin} attribute on the
 * consumer class. When the attribute carries no explicit version,
 * the installed package version is resolved from Composer's
 * `InstalledVersions` using the plugin name.
 *
 * Plugin authors include the trait on their Bundle class so they
 * don't have to write `getName()` / `getVersion()` / `boot()` by hand:
 *
 * ```php
 * #[AsPlugin(name: 'polysource/adapter-messenger')]
 * final class PolysourceAdapterMessengerBundle extends Bundle
 *     implements AdminPluginInterface
 * {
 *     use HasPluginMetadata;
 * }
 * ```
 *
 * `boot()` defaults to a no-op. Plugins that need custom bootstrap
 * logic override it normally — the trait declares it as a regular
 * method, not `final`, so subclasses win.
 *
 * @since 0.1.0
 */
trait HasPluginMetadata
{
    public function getPluginName(): string
    {
        return $this->resolvePluginAttribute()->name;
    }

    public function getPluginVersion(): string
    {
        $attribute = $this->resolvePluginAttribute();
        if (null !== $attribute->version) {
            return $attribute->version;
        }

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($attribute->name)) {
            return InstalledVersions::getPrettyVersion($attribute->name) ?? 'unknown';
        }

        return 'unknown';
    }

    private function resolvePluginAttribute(): AsPlugin
    {
        $attributes = (new ReflectionClass($this))->getAttributes(AsPlugin::class);
        if ([] === $attributes) {
            throw new RuntimeException(\sprintf('Class %s uses HasPluginMetadata but has no #[AsPlugin] attribute. Either add the attribute or implement getName()/getVersion() directly.', static::class));
        }

        return $attributes[0]->newInstance();
    }
}
