<?php

declare(strict_types=1);

namespace Polysource\Filter\DependencyInjection;

/**
 * Named-predicate helpers for feature gating in DI extensions.
 *
 * Polysource ships optional features (saved views, column
 * preferences, bulk-action history, recent records, filter-url
 * tokens) each gated on a combination of:
 *
 *   - Symfony bundle availability (DoctrineBundle, SecurityBundle,
 *     TwigBundle)
 *   - Polysource sub-package availability (SavedViewService class
 *     loaded means polysource/filter has SavedView code wired)
 *
 * Before this helper the same checks were inlined at 5+ sites per
 * extension as raw `array_key_exists('X', $bundles)` / `class_exists`
 * calls. The intent was lost in the syntax — now every gate has a
 * name that documents WHY it's there.
 *
 * Pure-function namespace. No state, no construction.
 *
 * Extracted in v0.10.0 per audit task #67. The full per-feature
 * loader split (FeatureLoader classes per optional feature)
 * remains deferred to v0.11 — see the audit synthesis for plan.
 *
 * @since 0.10.0
 */
final class FeatureGate
{
    /**
     * @param array<string, mixed>|mixed $bundles the `kernel.bundles` parameter
     */
    public static function hasBundle(mixed $bundles, string $bundleName): bool
    {
        return \is_array($bundles) && \array_key_exists($bundleName, $bundles);
    }

    /**
     * @param array<string, mixed>|mixed $bundles
     */
    public static function hasDoctrineBundle(mixed $bundles): bool
    {
        return self::hasBundle($bundles, 'DoctrineBundle');
    }

    /**
     * @param array<string, mixed>|mixed $bundles
     */
    public static function hasSecurityBundle(mixed $bundles): bool
    {
        return self::hasBundle($bundles, 'SecurityBundle');
    }

    /**
     * @param array<string, mixed>|mixed $bundles
     */
    public static function hasTwigBundle(mixed $bundles): bool
    {
        return self::hasBundle($bundles, 'TwigBundle');
    }

    /**
     * @param array<string, mixed>|mixed $bundles
     */
    public static function hasEasyAdminBundle(mixed $bundles): bool
    {
        return self::hasBundle($bundles, 'EasyAdminBundle');
    }

    /**
     * Saved-view stack requires Doctrine for persistence (the only
     * shipped storage) AND Security for the voter that enforces
     * ownership. Hosts that lack either get no saved-view services
     * registered; the Twig extension stays callable but returns
     * empty markup (cf. v0.1.4 ownership move).
     *
     * @param array<string, mixed>|mixed $bundles
     */
    public static function savedViewsAvailable(mixed $bundles): bool
    {
        return self::hasDoctrineBundle($bundles) && self::hasSecurityBundle($bundles);
    }
}
