<?php

declare(strict_types=1);

/*
 * Polysource preview app — entry point used by `make preview`.
 *
 * Boots a tiny Symfony kernel with the bundle + twig theme and a single
 * fixture resource (Feature flags). Designed to be served via
 * `php -S 0.0.0.0:8080 -t examples/preview examples/preview/index.php`.
 *
 * NOT for production. NOT shipped on Packagist.
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/Resources.php';
require __DIR__ . '/PreviewState.php';
require __DIR__ . '/PreviewKernel.php';

use Polysource\Examples\Preview\PreviewKernel;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', \PHP_URL_PATH) ?: '/';

// Redirect the bare root to the only resource so the user sees something
// immediately.
if ('/' === $path) {
    (new RedirectResponse('/admin/flags'))->send();
    exit;
}

$kernel = new PreviewKernel('dev', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
