<?php

declare(strict_types=1);

use Polysource\EasyAdminFilterBridge\Controller\SavedViewController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Routes shipped by the bridge for saved-view CRUD. Hosts opt in by
 * adding to their `config/routes.yaml`:
 *
 *     polysource_easyadmin_filter_bridge:
 *         resource: '@PolysourceEasyAdminFilterBridge/config/routes.php'
 *         type: php
 *
 * This wires:
 *   - POST /admin/saved-views                → polysource_saved_view_create
 *   - POST /admin/saved-views/{id}/delete    → polysource_saved_view_delete
 *
 * Host can override by declaring routes with the same names BEFORE
 * this resource is loaded.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import(SavedViewController::class, 'attribute');
};
