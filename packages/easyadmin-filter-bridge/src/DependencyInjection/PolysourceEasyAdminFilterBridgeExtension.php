<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\DependencyInjection;

use Polysource\EasyAdminFilterBridge\Chip\ChipValueFormatter;
use Polysource\EasyAdminFilterBridge\Configurator\ArrayFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\BooleanFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\ChoiceFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\ComparisonFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\DateTimeFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\EntityFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\NumericFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Configurator\TextFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Controller\ColumnOrderController;
use Polysource\EasyAdminFilterBridge\Controller\ExportController;
use Polysource\EasyAdminFilterBridge\Controller\FilterUrlTokenController;
use Polysource\EasyAdminFilterBridge\Controller\MatchingCountController;
use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use Polysource\EasyAdminFilterBridge\EventListener\FilterFormThemeRegistrationSubscriber;
use Polysource\EasyAdminFilterBridge\EventListener\FilterMarkerProcessor;
use Polysource\EasyAdminFilterBridge\EventListener\FilterSessionPersistenceSubscriber;
use Polysource\EasyAdminFilterBridge\EventListener\SavedViewApplySubscriber;
use Polysource\EasyAdminFilterBridge\Export\Exporter;
use Polysource\EasyAdminFilterBridge\Filter\UrlFilterApplier;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedTextFilterType;
use Polysource\EasyAdminFilterBridge\Twig\Extension\BulkScopeExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\CellFilterMenuExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ChipExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ColumnReorderExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ColumnWidthExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\EmptyStateExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FilterShortUrlExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FilterTreeExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\FrozenColumnExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\KeyboardShortcutsExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\QuickFilterRowExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowClassExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowDensityExtension;
use Polysource\EasyAdminFilterBridge\Twig\Extension\ToastExtension;
use Polysource\EasyAdminFilterBridge\Twig\FilterTreeBuilder;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * DI extension for `polysource/easyadmin-filter-bridge`.
 *
 * Registers the `FilterConfiguratorInterface` services and the enhanced
 * Symfony FormTypes. Auto-tagging is handled by EasyAdmin's own
 * `registerForAutoconfiguration(FilterConfiguratorInterface::class)`
 * (cf. `EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\EasyAdminExtension`),
 * so we just register the services with autoconfiguration enabled and
 * EasyAdmin picks them up.
 *
 * Form types tagged `form.type` are auto-discovered by Symfony Form when
 * autoconfiguration is on (default).
 */
final class PolysourceEasyAdminFilterBridgeExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Auto-register our form theme into the host app's `twig.form_themes`
     * config. Means a host that does `composer require
     * polysource/easyadmin-filter-bridge` immediately gets the rendered
     * preset buttons / chips / data-attributes — no Twig config to write.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');
        if (!\is_array($bundles) || !\in_array('TwigBundle', array_keys($bundles), true)) {
            return;
        }

        // 1) Register our form theme so the enhanced widgets render
        //    without any host-side Twig config.
        $container->prependExtensionConfig('twig', [
            'form_themes' => [
                '@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig',
            ],
        ]);

        // 2) Splice our `Resources/views/` into the `@EasyAdmin`
        //    Twig namespace at higher priority so that
        //    `Resources/views/crud/index.html.twig` is picked up
        //    instead of the upstream one. The override extends
        //    `@!EasyAdmin/crud/index.html.twig` to fall back to
        //    the original within Twig — no infinite loop.
        $bridgeViewsDir = \dirname(__DIR__, 2) . '/Resources/views';
        $container->prependExtensionConfig('twig', [
            'paths' => [
                $bridgeViewsDir => 'EasyAdmin',
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $container
            ->register(EnhancedDateTimeFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedBooleanFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedTextFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedNumericFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedChoiceFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedComparisonFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedArrayFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EnhancedEntityFilterType::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(DateTimeFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(BooleanFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(TextFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(NumericFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(ChoiceFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(ComparisonFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(ArrayFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(EntityFilterEnhancer::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        // Chip-rendering services: ChipValueFormatter resolves
        // boolean/entity values to human-readable strings; ChipExtension
        // exposes it to Twig as `polysource_chip_value()`.
        $container
            ->register(ChipValueFormatter::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        $container
            ->register(ChipExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // RowClassExtension — Twig function `polysource_row_class(...)`
        // exposing a property-to-CSS-class map for table row colouring.
        // Stateless: no constructor args, autoconfigured as a twig.extension.
        $container
            ->register(RowClassExtension::class)
            ->setAutoconfigured(true)
        ;

        // CellFilterMenuExtension (v0.4.0) — Twig functions
        // `polysource_cell_filter_menu(...)` and `polysource_cell_filter_url(...)`
        // for the "filter from cell value" UX. Autowires
        // RequestStack so it can read the current URL and build
        // filter slices preserving the existing query.
        $container
            ->register(CellFilterMenuExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // EmptyStateExtension (v0.4.0) — Twig helpers for the
        // empty-state design system. Autowires RequestStack to
        // read the current `?filters[...]` slice for the
        // "active filters?" probe and the "clear filters URL" CTA.
        $container
            ->register(EmptyStateExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // QuickFilterRowExtension (v0.4.0) — `polysource_quick_filter_row(...)`.
        // Renders a tiny GET form per column header that submits to
        // the current URL preserving every other query param (other
        // filters, sort, page). Server-side baseline; no JS.
        $container
            ->register(QuickFilterRowExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // BulkScopeExtension (v0.4.0) — Twig helpers for cross-page
        // selection + bulk dry-run. Renders the "apply to all matching"
        // checkbox + builds the dry-run URL. The host's bulk-action
        // endpoint reads the `bulk_scope` request body field and
        // branches on `?dry_run=1` to return a JSON preview.
        $container
            ->register(BulkScopeExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // FrozenColumnExtension (v0.5.0) — `polysource_frozen_column(side, offset)`
        // emits `class="..." style="..."` attributes pinning a table
        // cell to the left or right edge via `position: sticky`. Pure
        // server-side, no JS, no external CSS pipeline. Stateless.
        $container
            ->register(FrozenColumnExtension::class)
            ->setAutoconfigured(true)
        ;

        // RowDensityExtension (v0.5.0) — 2-state row density toggle
        // (compact ↔ normal). Reads `?density=X` from the URL, emits
        // either `table-sm` or empty for the `<table>` class, and
        // renders a 2-anchor Bootstrap btn-group toggle preserving
        // every other query param.
        $container
            ->register(RowDensityExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // ColumnWidthExtension (v0.5.0) — `polysource_column_width_style(view, property)`
        // emits the `style="width: Xpx"` attribute from the saved
        // view's columnWidths map. Stateless.
        $container
            ->register(ColumnWidthExtension::class)
            ->setAutoconfigured(true)
        ;

        // KeyboardShortcutsExtension (v0.5.0) — renders a
        // server-side cheat sheet of the recommended shortcuts via
        // `polysource_keyboard_shortcuts_help()`. Stateless.
        $container
            ->register(KeyboardShortcutsExtension::class)
            ->setAutoconfigured(true)
        ;

        // ToastExtension (v0.5.0) — `polysource_toasts()` renders
        // Symfony flash messages as Bootstrap-styled alerts pinned
        // top-right (toast-style placement, alert markup so they
        // render without JS per ADR-027). Auto-picks up EA's own
        // bulk-action success/warning flashes.
        $container
            ->register(ToastExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        // Filter tree builder + Twig function — consumed by
        // `crud/includes/_filters_modal.html.twig` to inject the
        // groups/tabs JSON tree on `#modal-filters` so the
        // Stimulus controller can reorganise the filter form.
        $container
            ->register(FilterTreeBuilder::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        $container
            ->register(FilterTreeExtension::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
        ;

        $container
            ->register(FilterSessionPersistenceSubscriber::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        $container
            ->register(FilterFormThemeRegistrationSubscriber::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        // Walks FilterConfigDto, propagates Polysource::tab/group
        // markers to subsequent filters, removes the markers.
        $container
            ->register(FilterMarkerProcessor::class)
            ->setAutoconfigured(true)
            ->setAutowired(true)
        ;

        // Default SavedViewController + ?view=<id> subscriber. Together
        // they make the saved-views dropdown work end-to-end out of
        // the box — host can override by re-declaring the routes at
        // higher priority.
        if (class_exists(\Polysource\Filter\SavedView\SavedViewService::class)) {
            $container
                ->register(SavedViewController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;

            $container
                ->register(SavedViewApplySubscriber::class)
                ->setAutoconfigured(true)
                ->setAutowired(true)
            ;
        }

        // Exporter — stateless streaming CSV/XLSX writer (v0.3.0).
        // Always registered (no deps beyond php standard lib). The XLSX
        // backend self-gates on openspout availability at runtime.
        $container
            ->register(Exporter::class)
            ->setAutowired(true)
            ->setPublic(true)
        ;

        // ExportController + MatchingCountController — GET endpoints
        // gated on DoctrineBundle being loaded (they need an
        // EntityManager). Hosts without Doctrine get no routes.
        //
        // UrlFilterApplier (v0.5.0) is the shared service both
        // controllers depend on for the `?filters[...]` URL slice
        // → Doctrine WHERE translation.
        $bundlesForExport = $container->hasParameter('kernel.bundles')
            ? $container->getParameter('kernel.bundles')
            : [];
        if (
            interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && \is_array($bundlesForExport)
            && \array_key_exists('DoctrineBundle', $bundlesForExport)
        ) {
            $container
                ->register(UrlFilterApplier::class)
                ->setAutowired(true)
                ->setPublic(false)
            ;

            $container
                ->register(ExportController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;

            $container
                ->register(MatchingCountController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;
        }

        // FilterUrlToken (v0.5.0) — controller + Twig helpers for
        // short shareable filter URLs. Gated on the filter
        // package's FilterUrlTokenService being available
        // (registered when Doctrine is loaded).
        if (
            class_exists(\Polysource\Filter\FilterUrlToken\FilterUrlTokenService::class)
            && interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && \is_array($bundlesForExport)
            && \array_key_exists('DoctrineBundle', $bundlesForExport)
        ) {
            $container
                ->register(FilterUrlTokenController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;

            $container
                ->register(FilterShortUrlExtension::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
            ;
        }

        // ColumnPreferenceController — POST /admin/polysource/column-preferences/{resource}
        // (v0.3.0). Gated on the SAME conditions as the filter
        // package's service registration: the class exists, the
        // DoctrineBundle is loaded, the SecurityBundle is loaded.
        // Without those the upstream service isn't registered and
        // autowiring the controller would crash DI compile.
        $bundles = $container->hasParameter('kernel.bundles')
            ? $container->getParameter('kernel.bundles')
            : [];
        $hasDoctrineBundle = \is_array($bundles) && \array_key_exists('DoctrineBundle', $bundles);
        $hasSecurity = \is_array($bundles) && \array_key_exists('SecurityBundle', $bundles);
        if (
            class_exists(\Polysource\Filter\ColumnPreference\ColumnPreferenceService::class)
            && interface_exists(\Doctrine\ORM\EntityManagerInterface::class)
            && $hasDoctrineBundle
            && $hasSecurity
        ) {
            $container
                ->register(\Polysource\EasyAdminFilterBridge\Controller\ColumnPreferenceController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;

            // ColumnReorderExtension + ColumnOrderController (v0.5.0).
            // Same gating as ColumnPreferenceController: depend on
            // ColumnPreferenceService which itself is registered only
            // when the host has Doctrine + Security bundles.
            $container
                ->register(ColumnReorderExtension::class)
                ->setAutowired(true)
                ->setAutoconfigured(true)
            ;

            $container
                ->register(ColumnOrderController::class)
                ->setAutowired(true)
                ->setPublic(true)
                ->addTag('controller.service_arguments')
            ;
        }
    }

    public function getAlias(): string
    {
        return 'polysource_easyadmin_filter_bridge';
    }
}
