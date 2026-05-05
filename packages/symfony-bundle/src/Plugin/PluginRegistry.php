<?php

declare(strict_types=1);

namespace Polysource\Bundle\Plugin;

use Polysource\Core\Plugin\AdminPluginInterface;

/**
 * Runtime registry of installed Polysource plugins.
 *
 * Built at compile time by `PluginCompilerPass`, which collects every
 * service tagged `polysource.plugin`. Hosts retrieve the registry from
 * the container (`Polysource\Bundle\Plugin\PluginRegistry`) for
 * introspection / debugging.
 *
 * Plugins are indexed by `getName()` (a globally-unique identifier per
 * `AdminPluginInterface` contract). Names collide-protect: registering
 * two plugins with the same name keeps only the first one declared.
 *
 * Per ADR-018 §4. The registry shape is stable from v0.1.0 — adding a
 * method = breaking change.
 *
 * @since 0.1.0
 */
final class PluginRegistry
{
    /** @var array<string, AdminPluginInterface> */
    private readonly array $byName;

    /**
     * @param iterable<AdminPluginInterface> $plugins
     */
    public function __construct(iterable $plugins)
    {
        $byName = [];
        foreach ($plugins as $plugin) {
            $name = $plugin->getPluginName();
            // First-wins on name collision. Collisions are real bugs;
            // we don't silently overwrite to avoid masking the issue.
            // PluginCompilerPass logs duplicates at compile-time.
            $byName[$name] ??= $plugin;
        }
        $this->byName = $byName;
    }

    /**
     * @return iterable<AdminPluginInterface>
     */
    public function all(): iterable
    {
        return array_values($this->byName);
    }

    public function get(string $name): ?AdminPluginInterface
    {
        return $this->byName[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->byName[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->byName);
    }
}
