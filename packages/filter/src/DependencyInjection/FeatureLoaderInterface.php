<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * One optional feature's DI wiring, extracted from the package
 * extension's monolithic `load()` (ADR-0032, audit task #67 phase 2).
 *
 * `supports()` carries the feature's ENTIRE gate — bundle
 * availability (via {@see FeatureGate}) plus any
 * `class_exists`/`interface_exists` probes. `load()` never re-tests
 * the gate; it may still branch internally for the v0.1.4
 * nullable-service pattern (a Twig extension registered with null
 * arguments when its backing storage isn't wired), which is wiring,
 * not gating.
 *
 * Loaders are listed EXPLICITLY in the extension's
 * `featureLoaders()` — no tag-based discovery: DI wiring must stay
 * readable top-to-bottom.
 *
 * Shared with `polysource/easyadmin-filter-bridge` (which already
 * depends on this package — same precedent as {@see FeatureGate}).
 *
 * @internal not part of the public API — hosts extend Polysource
 *           through the documented extension points, not by
 *           implementing package loaders
 *
 * @since 0.11.0
 */
interface FeatureLoaderInterface
{
    /**
     * @param array<string, mixed>|mixed $bundles the `kernel.bundles` parameter
     */
    public function supports(mixed $bundles): bool;

    /**
     * @param array<string, mixed>|mixed $bundles
     */
    public function load(ContainerBuilder $container, mixed $bundles): void;
}
