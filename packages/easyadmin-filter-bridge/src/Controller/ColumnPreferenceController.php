<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Polysource\Filter\ColumnPreference\ColumnPreferenceService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * HTTP endpoint to update the current user's column-visibility
 * preferences for a given resource.
 *
 * UX contract:
 * - The EA index template renders a dropdown menu with a checkbox
 *   per column (checked = visible).
 * - Submitting the form POSTs here with the list of *visible*
 *   property names; the server computes the inverted *hidden* list
 *   and persists.
 * - On success the user is redirected back to the previous URL
 *   (the Referer or the resource's index URL).
 *
 * Security:
 * - CSRF token required (form-level).
 * - The service silently no-ops for anonymous users, so a logged-out
 *   POST simply has no effect.
 *
 * @since 0.3.0
 */
final class ColumnPreferenceController
{
    public function __construct(
        private readonly ColumnPreferenceService $service,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(
        path: '/admin/polysource/column-preferences/{resource}',
        name: 'polysource_column_preferences_update',
        methods: ['POST'],
        requirements: ['resource' => '[A-Za-z0-9_.\\\\:-]+'],
    )]
    public function update(Request $request, string $resource): Response
    {
        $submittedToken = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(
            new CsrfToken('polysource_column_preferences_' . $resource, $submittedToken),
        )) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        /** @var list<string> $allColumns */
        $allColumns = $this->normaliseList($request->request->all('columns'));
        /** @var list<string> $visibleColumns */
        $visibleColumns = $this->normaliseList($request->request->all('visible'));

        // Compute the hidden set from the visible submission.
        $hidden = [];
        foreach ($allColumns as $column) {
            if (!\in_array($column, $visibleColumns, true)) {
                $hidden[] = $column;
            }
        }

        $this->service->setHiddenColumns($resource, $hidden);

        // Redirect to where the form came from. The Referer header is
        // present for any non-AJAX POST submitted from an EA index
        // page (the standard call path). For the rare cases without
        // a Referer (curl, browser privacy mode), fall back to `/`
        // — `/admin` would assume a specific EA mount which breaks
        // on multi-tenant hosts (`/{channel}/admin`) and on apps
        // that mount EA under a custom prefix. Surfaced 2026-05-14
        // dogfooding round 3 (friction C9).
        $referer = (string) $request->headers->get('Referer', '/');

        return new RedirectResponse($referer);
    }

    /**
     * @param array<int|string, mixed> $raw
     *
     * @return list<string>
     */
    private function normaliseList(array $raw): array
    {
        $out = [];
        foreach ($raw as $value) {
            if (\is_string($value) && '' !== $value) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }
}
