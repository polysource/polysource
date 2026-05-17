<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor\Check;

use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;

/**
 * Verifies the runtime PHP version meets Polysource's minimum (8.1).
 * Per ADR-015 the supported baseline is fixed at 8.1.
 *
 * @since 0.9.0
 */
final class PhpVersionCheck implements HealthCheckInterface
{
    private const REQUIRED = '8.1.0';

    public function getName(): string
    {
        return 'PHP version';
    }

    public function run(): HealthCheckResult
    {
        $current = \PHP_VERSION;

        if (version_compare($current, self::REQUIRED, '>=')) {
            return HealthCheckResult::pass(\sprintf('%s (>= %s)', $current, self::REQUIRED));
        }

        return HealthCheckResult::fail(
            \sprintf('%s — Polysource requires >= %s', $current, self::REQUIRED),
        );
    }
}
