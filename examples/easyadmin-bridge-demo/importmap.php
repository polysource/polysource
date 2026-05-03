<?php

declare(strict_types=1);

/**
 * Returns the importmap for this demo. AssetMapper consumes this at
 * request time and emits the corresponding `<script type="importmap">`
 * tag in the HTML head.
 *
 * - `app` is the entry point loaded into EasyAdmin via
 *   `Crud::addAssetMapperEntry('app')`.
 * - `@hotwired/stimulus` is downloaded from JSDelivr by
 *   `bin/console importmap:install`.
 * - `@symfony/stimulus-bundle` is NOT on npm — it ships with the
 *   Composer package, so we point at the local file directly.
 *   Convention from `vendor/symfony/stimulus-bundle/assets/package.json`'s
 *   `symfony.importmap` block.
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
        'path' => '@symfony/stimulus-bundle/loader.js',
    ],
];
