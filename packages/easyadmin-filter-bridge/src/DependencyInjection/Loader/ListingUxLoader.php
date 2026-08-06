<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection\Loader;

use Polysource\EasyAdminFilterBridge\Twig\Extension\BulkScopeExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\CellFilterMenuExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ColumnWidthExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\EmptyStateExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FrozenColumnExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\KeyboardShortcutsExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\QuickFilterRowExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowClassExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowDensityExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ToastExtension;
use Polysource\Filter\DependencyInjection\FeatureLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The listing-UX Twig helper extensions (v0.4.0 → v0.5.0) — each a
 * server-side-first helper the host composes from its templates,
 * never auto-injected (cf. ADR-027/ADR-028):
 *
 * - RowClassExtension — `polysource_row_class(...)`, property-to-CSS
 *   map for table row colouring (stateless).
 * - CellFilterMenuExtension — "filter from cell value" dropdown;
 *   autowires RequestStack to preserve the existing query.
 * - EmptyStateExtension — active-filters probe + clear-filters URL
 *   for empty-state CTAs.
 * - QuickFilterRowExtension — per-column GET form preserving every
 *   other query param. Server-side baseline; no JS.
 * - BulkScopeExtension — cross-page selection + bulk dry-run helpers.
 * - FrozenColumnExtension — `position: sticky` pinning attributes.
 * - RowDensityExtension — 2-state density toggle from `?density=X`.
 * - ColumnWidthExtension — width style from the saved view's map.
 * - KeyboardShortcutsExtension — server-rendered cheat sheet.
 * - ToastExtension — flashes as toast-placed Bootstrap alerts
 *   (alert markup so they render without JS per ADR-027).
 *
 * All autowired for their optional RequestStack / translator; the
 * stateless ones tolerate a bare construction in tests.
 *
 * @internal
 *
 * @since 0.11.0
 */
final class ListingUxLoader implements FeatureLoaderInterface
{
    private const SERVICES = [
        RowClassExtension::class,
        CellFilterMenuExtension::class,
        EmptyStateExtension::class,
        QuickFilterRowExtension::class,
        BulkScopeExtension::class,
        FrozenColumnExtension::class,
        RowDensityExtension::class,
        ColumnWidthExtension::class,
        KeyboardShortcutsExtension::class,
        ToastExtension::class,
    ];

    public function supports(mixed $bundles): bool
    {
        return true;
    }

    public function load(ContainerBuilder $container, mixed $bundles): void
    {
        foreach (self::SERVICES as $class) {
            $container
                ->register($class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
            ;
        }
    }
}
