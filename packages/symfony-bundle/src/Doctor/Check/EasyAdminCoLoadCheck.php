<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor\Check;

use Polysource\Bundle\Doctor\HealthCheckInterface;
use Polysource\Bundle\Doctor\HealthCheckResult;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Detects the common-but-silent misconfig: the EasyAdmin filter
 * bridge is registered but EasyAdminBundle itself is missing. The
 * bridge ships C1 guards so it stays a no-op rather than crashing,
 * but the user clearly intended to use EA — surfacing the gap saves
 * a debugging session.
 *
 * @since 0.9.0
 */
final class EasyAdminCoLoadCheck implements HealthCheckInterface
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    public function getName(): string
    {
        return 'EA bridge co-load';
    }

    public function run(): HealthCheckResult
    {
        $loaded = array_keys($this->kernel->getBundles());
        $hasBridge = \in_array('PolysourceEasyAdminFilterBridgeBundle', $loaded, true);
        $hasEa = \in_array('EasyAdminBundle', $loaded, true);

        if (!$hasBridge) {
            return HealthCheckResult::pass('PolysourceEasyAdminFilterBridgeBundle not loaded — skip.');
        }

        if ($hasEa) {
            return HealthCheckResult::pass('EasyAdminBundle is loaded alongside the bridge.');
        }

        // The bridge ships C1 guards (v0.5.7) so it's a no-op on
        // EA-less kernels. The DI extension + Bundle::boot() both
        // short-circuit. Calling it a WARN: the host probably forgot
        // to load EA, but the bridge won't crash.
        return HealthCheckResult::warn(
            'Bridge bundle is loaded but EasyAdminBundle is NOT. '
            . 'Bridge is a no-op on this kernel (v0.5.7 C1 guard). '
            . 'Did you mean to scope the bridge to an EA-only kernel?',
        );
    }
}
