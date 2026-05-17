<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor;

/**
 * Immutable result of a doctor health check. Three statuses:
 *
 *   - PASS — healthy
 *   - WARN — non-fatal gap (e.g. host has no Doctrine, so saved-views
 *            features won't work, but the bundle is OK to keep installed)
 *   - FAIL — a fix is needed before the host can use Polysource safely
 *
 * Plus a free-form detail string surfaced in the doctor table.
 *
 * @since 0.9.0
 */
final class HealthCheckResult
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * @param self::STATUS_* $status
     */
    public function __construct(
        public readonly string $status,
        public readonly string $detail,
    ) {
    }

    public static function pass(string $detail): self
    {
        return new self(self::STATUS_PASS, $detail);
    }

    public static function warn(string $detail): self
    {
        return new self(self::STATUS_WARN, $detail);
    }

    public static function fail(string $detail): self
    {
        return new self(self::STATUS_FAIL, $detail);
    }
}
