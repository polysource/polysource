<?php

declare(strict_types=1);

/**
 * Dev-router for PHP's built-in web server.
 *
 * `php -S` invokes this script for every HTTP request. We return
 * `false` for paths that resolve to an existing static file under
 * `public/` — PHP's built-in server then serves that file with the
 * correct `Content-Type` (e.g. `text/css` for `.css`, `application/javascript`
 * for `.js`). For everything else we include `index.php` so the
 * Symfony kernel handles the request.
 *
 * Without this, `index.php` intercepts every request and replies
 * with `Content-Type: text/html` — modern browsers strict-block
 * stylesheets/scripts when the MIME type doesn't match the resource
 * type, which manifests as an unstyled admin page even though the
 * CSS file is reachable.
 */
$path = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
$file = __DIR__ . $path;

if ('/' !== $path && file_exists($file) && !is_dir($file)) {
    // Let PHP built-in server stream the file with the proper MIME.
    return false;
}

require __DIR__ . '/index.php';
