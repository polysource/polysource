<?php

declare(strict_types=1);

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '8.0.13',
    ],
    'bootstrap' => [
        'version' => '5.3.3',
    ],
    '@polysource/search/controllers/cmdk_controller.js' => [
        'path' => '@polysource/search/controllers/cmdk_controller.js',
    ],
    '@polysource/bulk-async/controllers/progress_controller.js' => [
        'path' => '@polysource/bulk-async/controllers/progress_controller.js',
    ],
    '@polysource/filter/controllers/row_details_controller.js' => [
        'path' => '@polysource/filter/controllers/row_details_controller.js',
    ],
];
