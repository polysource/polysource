<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP endpoint that resolves a filter URL token and redirects
 * to the full filter URL.
 *
 * URL pattern: `/admin/polysource/f/{token}`. Resolves the
 * stored slice + resource, builds the long URL, redirects.
 *
 * The redirect target URL is constructed by the caller —
 * typically the host's EA index for the resource. To keep the
 * controller framework-agnostic, the host opts into the redirect
 * pattern by reading the `?index` query argument; otherwise the
 * controller redirects to a `?token` of the current URL (a 302
 * loop hosts MUST NOT trigger — the token MUST come with an
 * `?index=` argument in the short URL, populated by the bridge's
 * Twig helper).
 *
 * Security: hosts MUST gate the route behind their EA firewall;
 * resolution doesn't expose ownership but the redirected URL
 * lands on a host-protected page that will enforce its own
 * access checks.
 *
 * @since 0.5.0
 */
final class FilterUrlTokenController
{
    public function __construct(private readonly FilterUrlTokenService $service)
    {
    }

    #[Route(
        path: '/admin/polysource/f/{token}',
        name: 'polysource_filter_url_token_resolve',
        methods: ['GET'],
        requirements: ['token' => '[a-f0-9]{12}'],
    )]
    public function resolve(Request $request, string $token): Response
    {
        $record = $this->service->resolve($token);
        if (null === $record) {
            throw new NotFoundHttpException(\sprintf('Unknown filter URL token "%s".', $token));
        }

        $index = (string) $request->query->get('index', '');
        // Same-origin redirect target only. A single leading slash is
        // an absolute PATH; `//host` is a protocol-RELATIVE URL and
        // `/\host` is treated the same by browsers that normalise
        // backslashes — both would turn this endpoint into an open
        // redirect. Surfaced 2026-08-06 while writing the endpoint's
        // regression suite.
        if (
            '' === $index
            || !str_starts_with($index, '/')
            || str_starts_with($index, '//')
            || str_starts_with($index, '/\\')
        ) {
            throw new NotFoundHttpException('Missing or invalid `index` redirect target.');
        }

        $existingQuery = '';
        if (str_contains($index, '?')) {
            [$index, $existingQuery] = explode('?', $index, 2);
            parse_str($existingQuery, $existingParams);
        } else {
            $existingParams = [];
        }

        $query = array_merge($existingParams, ['filters' => $record->filtersSlice]);
        $qs = http_build_query($query, '', '&', \PHP_QUERY_RFC3986);

        return new RedirectResponse('' === $qs ? $index : $index . '?' . $qs);
    }
}
