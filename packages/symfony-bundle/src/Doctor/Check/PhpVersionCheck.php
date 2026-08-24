<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor\Check;

use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;

/**
 * Verifies the runtime PHP version meets Polysource's minimum (8.2).
 * Per ADR-015 as amended by ADR-011, the v1.0 freeze raised the
 * supported baseline from 8.1 to 8.2; every package advertises
 * `php: ">=8.2"`. There is no upper bound: 8.5 is covered by CI.
 *
 * @since 0.9.0
 */
final class PhpVersionCheck implements HealthCheckInterface
{
    private const REQUIRED = '8.2.0';

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
