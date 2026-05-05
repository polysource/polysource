<?php

declare(strict_types=1);

use Polysource\BulkAsync\Controller\ProgressController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('polysource_bulk_async_progress', '/admin/bulk-jobs/{id}/progress')
        ->controller(ProgressController::class)
        ->methods(['GET'])
        ->requirements(['id' => '[0-9a-fA-F\-]{36}']);
};
