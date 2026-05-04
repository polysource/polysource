<?php

declare(strict_types=1);

namespace Polysource\Bundle\Routing;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Helper that resolves Polysource route names from resource slugs.
 *
 * Cf. ADR-003 §Url generation — users typically call this through a Twig
 * helper rather than directly. Future Twig globals / helpers will be
 * wired through `polysource/twig-theme`.
 */
final class PolysourceUrlGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function index(string $resourceName): string
    {
        return $this->urlGenerator->generate(
            'polysource_' . PolysourceRouteLoader::routeKey($resourceName) . '_index',
            ['resourceName' => $resourceName],
        );
    }

    public function detail(string $resourceName, string|int $recordId): string
    {
        return $this->urlGenerator->generate(
            'polysource_' . PolysourceRouteLoader::routeKey($resourceName) . '_detail',
            ['resourceName' => $resourceName, 'id' => (string) $recordId],
        );
    }

    public function action(string $resourceName, string|int $recordId, string $actionName): string
    {
        return $this->urlGenerator->generate(
            'polysource_' . PolysourceRouteLoader::routeKey($resourceName) . '_action',
            [
                'resourceName' => $resourceName,
                'id' => (string) $recordId,
                'action' => $actionName,
            ],
        );
    }

    public function bulkAction(string $resourceName, string $actionName): string
    {
        return $this->urlGenerator->generate(
            'polysource_' . PolysourceRouteLoader::routeKey($resourceName) . '_bulk_action',
            ['resourceName' => $resourceName, 'action' => $actionName],
        );
    }
}
