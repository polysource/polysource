<?php

declare(strict_types=1);

use Polysource\EasyAdminFilterBridge\Controller\ColumnOrderController;
use Polysource\EasyAdminFilterBridge\Controller\ColumnPreferenceController;
use Polysource\EasyAdminFilterBridge\Controller\ExportController;
use Polysource\EasyAdminFilterBridge\Controller\MatchingCountController;
use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Routes shipped by the bridge. Hosts opt in by adding to their
 * `config/routes.yaml`:
 *
 *     polysource_easyadmin_filter_bridge:
 *         resource: '@PolysourceEasyAdminFilterBridge/config/routes.php'
 *         type: php
 *
 * This wires:
 *   - POST /admin/saved-views                                → polysource_saved_view_create
 *   - POST /admin/saved-views/{id}/delete                    → polysource_saved_view_delete
 *   - POST /admin/saved-views/{id}/default                   → polysource_saved_view_toggle_default (v0.3.0)
 *   - POST /admin/polysource/column-preferences/{resource}   → polysource_column_preferences_update (v0.3.0)
 *   - GET  /admin/polysource/export/{resource}.{format}      → polysource_export (v0.3.0, filter-aware since v0.5.0)
 *   - GET  /admin/polysource/matching-count/{resource}       → polysource_matching_count (v0.5.0)
 *   - GET  /admin/polysource/column-order/{resource}/move    → polysource_column_order_move (v0.5.0)
 *
 * Host can override by declaring routes with the same names BEFORE
 * this resource is loaded.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import(SavedViewController::class, 'attribute');
    $routes->import(ColumnPreferenceController::class, 'attribute');
    $routes->import(ExportController::class, 'attribute');
    $routes->import(MatchingCountController::class, 'attribute');
    $routes->import(ColumnOrderController::class, 'attribute');
};
