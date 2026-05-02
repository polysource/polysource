<?php

declare(strict_types=1);

namespace Polysource\Bundle\Routing;

use LogicException;
use Polysource\Bundle\Controller\ActionController;
use Polysource\Bundle\Controller\DetailController;
use Polysource\Bundle\Controller\IndexController;
use Polysource\Bundle\Registry\ResourceRegistry;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generates physical routes for every registered Polysource resource.
 *
 * Cf. ADR-003 — for each resource we generate four routes:
 *   - GET    {prefix}/{slug}                  → IndexController::__invoke
 *   - GET    {prefix}/{slug}/{id}             → DetailController::__invoke
 *   - POST   {prefix}/{slug}/{id}/{action}    → ActionController::__invoke
 *   - POST   {prefix}/{slug}/batch/{action}   → ActionController::bulk
 *
 * Activate in the host app's `config/routes/polysource.yaml`:
 *
 *     polysource:
 *         resource: .
 *         type: polysource
 */
final class PolysourceRouteLoader extends Loader
{
    public const TYPE = 'polysource';

    public const ATTR_ACTION = '_polysource_action';

    public function __construct(
        private readonly ResourceRegistry $resources,
        private readonly string $urlPrefix = '/admin',
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $collection = new RouteCollection();
        $prefix = '/' . trim($this->urlPrefix, '/');

        // Track route keys to detect post-normalisation collisions
        // (e.g. `my-resource` and `my_resource` both → `my_resource`).
        $seenKeys = [];

        foreach ($this->resources->all() as $entity) {
            $slug = $entity->getName();
            $routeKey = self::routeKey($slug);
            $base = $prefix . '/' . $slug;

            if (isset($seenKeys[$routeKey])) {
                throw new LogicException(\sprintf('Polysource route key collision: resources "%s" and "%s" both normalise to "%s". Rename one of them.', $seenKeys[$routeKey], $slug, $routeKey));
            }
            $seenKeys[$routeKey] = $slug;

            $defaults = ['resourceName' => $slug];

            $collection->add(
                'polysource_' . $routeKey . '_index',
                new Route(
                    path: $base,
                    defaults: $defaults + [
                        '_controller' => IndexController::class . '::__invoke',
                        self::ATTR_ACTION => 'index',
                    ],
                    methods: ['GET'],
                ),
            );

            $collection->add(
                'polysource_' . $routeKey . '_detail',
                new Route(
                    path: $base . '/{id}',
                    defaults: $defaults + [
                        '_controller' => DetailController::class . '::__invoke',
                        self::ATTR_ACTION => 'detail',
                    ],
                    requirements: ['id' => '(?!batch$)[^/]+'],
                    methods: ['GET'],
                ),
            );

            // Bulk route registered BEFORE the parameterised action route so
            // that `/{slug}/batch/{action}` always wins over the latent
            // ambiguity with `/{slug}/{id=batch}/{action}`. The action
            // route's `id` requirement also rejects the literal "batch" as
            // a defensive belt-and-braces guard.
            $collection->add(
                'polysource_' . $routeKey . '_bulk_action',
                new Route(
                    path: $base . '/batch/{action}',
                    defaults: $defaults + [
                        '_controller' => ActionController::class . '::bulk',
                        self::ATTR_ACTION => 'bulk',
                    ],
                    requirements: ['action' => '[a-z][a-z0-9_-]*'],
                    methods: ['POST'],
                ),
            );

            $collection->add(
                'polysource_' . $routeKey . '_action',
                new Route(
                    path: $base . '/{id}/{action}',
                    defaults: $defaults + [
                        '_controller' => ActionController::class . '::__invoke',
                    ],
                    requirements: ['id' => '(?!batch$)[^/]+', 'action' => '[a-z][a-z0-9_-]*'],
                    methods: ['POST'],
                ),
            );
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    /**
     * Convert a resource slug into a Symfony-route-name-safe segment.
     *
     * Public so URL generators / Twig helpers can compute the canonical
     * route name without re-reading the resource registry.
     */
    public static function routeKey(string $slug): string
    {
        $segment = preg_replace('/[^a-zA-Z0-9]+/', '_', $slug) ?? '';
        $segment = trim($segment, '_');

        return '' === $segment ? 'unnamed' : strtolower($segment);
    }
}
