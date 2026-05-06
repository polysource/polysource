<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\EventListener;

use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Consumes the `?clear-view=1` query param emitted by the saved-views
 * dropdown's "Clear current view" item.
 *
 * Why a marker instead of a bare `href="?"`:
 *   - `?` only strips the URL query string. The session entry that
 *     `SavedViewService::rememberAsLastUsed()` wrote on the previous
 *     `?view=<id>` apply survives — so on the rendered page,
 *     `defaultFor()` resurrects the view as 'current' and the
 *     dropdown still highlights it. The user perceives the Clear
 *     button as randomly broken.
 *   - The marker is unambiguous: when present, we (a) call
 *     `forgetLastUsed()` to wipe the session entry, then (b) issue
 *     a 302 to the same path WITHOUT any query (no `view`, no
 *     `filter`, no `clear-view`). The user lands on a fully clean
 *     URL and the dropdown re-renders with the default label.
 *
 * Resource scope: the listener forgets the entry for whichever
 * resource the route is mounted on. We rely on the
 * `resourceName` request attribute (set by the Polysource route
 * loader) OR fall back to the path's last meaningful segment when
 * the listener fires on an EasyAdmin route. If neither yields a
 * resource name, the listener still issues the clean redirect —
 * the session entry is keyed per resource so losing one stale
 * entry isn't a regression.
 *
 * Priority < 32 so we run AFTER Symfony's RouterListener populates
 * `resourceName`, but well before any controller resolves so we
 * can short-circuit with a redirect.
 */
final class ClearSavedViewListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly SavedViewService $service,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 16],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $marker = (string) $request->query->get('clear-view', '');
        if ('' === $marker) {
            return;
        }

        // The marker carries the resource name to clear (URL-decoded
        // by Symfony when reading $request->query). Polysource pages
        // pass their slug ("audit-log"); EA pages pass the entity
        // FQCN ("App\\Entity\\Product"). The session entry is keyed
        // per resource — both forms are valid and unambiguous.
        $resourceName = '1' === $marker
            ? (\is_string($request->attributes->get('resourceName')) ? $request->attributes->get('resourceName') : null)
            : $marker;
        if (\is_string($resourceName) && '' !== $resourceName) {
            $this->service->forgetLastUsed($resourceName);
        }

        // Redirect to the same path with NO query at all — drops
        // `clear-view`, `view`, `filter`, `filters`, etc. in one
        // shot. The dropdown re-renders without a `current`
        // highlight and the table re-renders unfiltered.
        $event->setResponse(new RedirectResponse($request->getPathInfo()));
    }
}
