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
 * HTTP endpoint to reorder a column on the current user's
 * column-preference record. Two routes: "move left" and "move
 * right" — each swaps the target column with its neighbour in
 * the explicit order.
 *
 * Pure server-side per ADR-027 progressive enhancement — no JS
 * drag-and-drop required. Hosts who want a richer reorder UX
 * ship their own controller (and POST the full new order via
 * the {@see ColumnPreferenceService::setColumnOrder()} API
 * directly), but the anchor-based baseline ships with the
 * bridge so the feature works without any client-side code.
 *
 * Security:
 * - CSRF token required on the URL slice (form GET — the token
 *   lives in the query string since the endpoint is reached via
 *   anchor click). Hosts who'd rather use POST forms swap the
 *   route methods.
 * - The service silently no-ops for anonymous users.
 *
 * @since 0.5.0
 */
final class ColumnOrderController
{
    public function __construct(
        private readonly ColumnPreferenceService $service,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(
        path: '/admin/polysource/column-order/{resource}/move',
        name: 'polysource_column_order_move',
        methods: ['GET'],
        requirements: ['resource' => '[A-Za-z0-9_.\\\\:-]+'],
    )]
    public function move(Request $request, string $resource): Response
    {
        $token = (string) $request->query->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(
            new CsrfToken('polysource_column_order_' . $resource, $token),
        )) {
            return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
        }

        $property = (string) $request->query->get('property', '');
        $direction = (string) $request->query->get('direction', '');

        if ('' === $property || !\in_array($direction, ['left', 'right'], true)) {
            return new Response('Missing or invalid property/direction.', Response::HTTP_BAD_REQUEST);
        }

        $defaultColumns = $this->normaliseList($request->query->all('columns'));
        if ([] === $defaultColumns) {
            return new Response('Missing columns slice.', Response::HTTP_BAD_REQUEST);
        }

        // Resolve the current effective order — host's default list
        // through the user's existing override (if any) so the move
        // is computed against what the user actually sees.
        $effective = $this->service->applyOrder($resource, $defaultColumns);

        $newOrder = $this->moveColumn($effective, $property, $direction);
        $this->service->setColumnOrder($resource, $newOrder);

        $referer = (string) $request->headers->get('Referer', '/admin');

        return new RedirectResponse($referer);
    }

    /**
     * Pure function — swap `$property` with its left/right neighbour
     * in `$order`. Returns the new list. Out-of-bounds moves (already
     * first/last) are no-ops.
     *
     * @param list<string> $order
     *
     * @return list<string>
     */
    private function moveColumn(array $order, string $property, string $direction): array
    {
        $index = array_search($property, $order, true);
        if (false === $index) {
            return $order;
        }

        $swapWith = 'left' === $direction ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= \count($order)) {
            return $order;
        }

        $next = $order;
        [$next[$index], $next[$swapWith]] = [$next[$swapWith], $next[$index]];

        return array_values($next);
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
