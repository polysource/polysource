<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor\Check;

use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;
use Polysource\Bundle\Plugin\PluginRegistry;

/**
 * Lists the Polysource plugins discovered by the {@see PluginRegistry}.
 * Empty registry usually means either no Polysource feature bundle is
 * loaded, or the `#[AsPlugin]` attribute is missing from a host's
 * custom plugin.
 *
 * @since 0.9.0
 */
final class PluginCheck implements HealthCheckInterface
{
    public function __construct(private readonly ?PluginRegistry $plugins = null)
    {
    }

    public function getName(): string
    {
        return 'Polysource plugins';
    }

    public function run(): HealthCheckResult
    {
        if (null === $this->plugins) {
            return HealthCheckResult::warn(
                'PluginRegistry not wired — `polysource:plugins:list` won\'t work either.',
            );
        }

        $rows = [];
        foreach ($this->plugins->all() as $plugin) {
            $rows[] = $plugin->getPluginName() . ' ' . $plugin->getPluginVersion();
        }

        if ([] === $rows) {
            return HealthCheckResult::warn(
                'No plugins discovered. Either no Polysource feature bundle is loaded, '
                . 'or the AsPlugin attribute is missing.',
            );
        }

        return HealthCheckResult::pass(\sprintf(
            '%d discovered (%s)',
            \count($rows),
            implode(', ', $rows),
        ));
    }
}
