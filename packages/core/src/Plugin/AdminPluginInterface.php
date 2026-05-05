<?php

declare(strict_types=1);

namespace Polysource\Core\Plugin;

/**
 * Marker contract for a Polysource plugin — a Symfony bundle that
 * contributes admin functionality (resources, widgets, bulk actions,
 * search providers, …) via Symfony DI tags.
 *
 * The interface is intentionally minimal. Per
 * {@link https://github.com/polysource/polysource/blob/main/docs/adr/0018-admin-plugin-interface-and-public-contracts.md ADR-018},
 * tag-based extension stays the **primary** contract for capabilities;
 * this interface adds only a metadata + introspection layer (so a host
 * can list installed plugins, debug versions, ship a `plugins:list`
 * command).
 *
 * Concrete plugins typically don't implement this interface directly —
 * they declare the {@see Attribute\AsPlugin}
 * attribute on their Bundle class and use the
 * {@see HasPluginMetadata} trait, which
 * provides default implementations reading from the attribute.
 *
 * @since 0.1.0
 */
interface AdminPluginInterface
{
    /**
     * Globally-unique plugin identifier.
     *
     * Convention: matches the Composer package name (e.g.
     * `"polysource/adapter-messenger"`, `"acme/admin-comments"`).
     * Used as the lookup key in `PluginRegistry`.
     *
     * Method is named `getPluginName()` rather than `getName()` because
     * `Symfony\Component\HttpKernel\Bundle\Bundle::getName()` is final
     * (returns the bundle's PHP class basename — different concept).
     * Plugins that ARE Symfony bundles can use both methods side by
     * side without conflict.
     *
     * @since 0.1.0
     */
    public function getPluginName(): string;

    /**
     * Plugin version as a semver string (e.g. `"0.1.0"`,
     * `"1.2.3-beta.1"`). Convention: matches the Composer package
     * version. Implementations may derive it via Composer's
     * `InstalledVersions` for accuracy, or hard-code it for simplicity.
     *
     * @since 0.1.0
     */
    public function getPluginVersion(): string;
}
