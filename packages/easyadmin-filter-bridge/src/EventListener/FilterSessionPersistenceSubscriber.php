<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Persists filter values in the HTTP session, scoped per CRUD controller
 * FQCN, so an operator returning to the index page after navigating away
 * sees their previous filters restored automatically.
 *
 * Behaviour, on every `BeforeCrudActionEvent` for the INDEX action:
 *
 * 1. **Save**: if the request URL carries a `filters[…]` query parameter,
 *    snapshot the current filter array into the session under
 *    `polysource.filters.{hash(controllerFqcn)}`.
 *
 * 2. **Reset detection**: if the request URL has NO filters AND the
 *    `Referer` header points at the same path (typical of EasyAdmin's
 *    `action-filters-reset` link, which goes from
 *    `/admin/product?filters[…]` back to `/admin/product`), treat it
 *    as an explicit user reset and CLEAR the session slot. Otherwise
 *    a saved filter would re-attach immediately and the X reset
 *    button would appear broken.
 *
 * 3. **Restore**: if neither save nor reset matches AND the session
 *    has saved filters for this CRUD, redirect to the same URL with
 *    the saved filters appended as `?filters[…]=…`.
 *    `BeforeCrudActionEvent::setResponse()` short-circuits the
 *    controller and returns the redirect immediately. This is what
 *    lets an operator open a row's edit page and come back with
 *    their filter intact.
 *
 * Scoping by `controllerFqcn` (hashed for shorter session keys) means
 * filters on `ProductCrudController` don't leak into
 * `OrderCrudController` — every CRUD has its own slot.
 *
 * No effect on non-INDEX actions (edit / detail / new) — those are
 * single-record operations and don't have a filter form.
 */
final class FilterSessionPersistenceSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY_PREFIX = 'polysource.filters.';
    private const FILTERS_QUERY_PARAM = 'filters';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCrudActionEvent::class => 'onBeforeCrudAction',
        ];
    }

    public function onBeforeCrudAction(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        if (null === $context) {
            return;
        }

        $crud = $context->getCrud();
        if (null === $crud) {
            return;
        }

        if (Action::INDEX !== $crud->getCurrentAction()) {
            return;
        }

        $controllerFqcn = $crud->getControllerFqcn();
        if (null === $controllerFqcn) {
            return;
        }

        $session = $this->getSession();
        if (null === $session) {
            return;
        }

        $request = $context->getRequest();
        $sessionKey = self::SESSION_KEY_PREFIX . hash('xxh128', $controllerFqcn);

        if ($request->query->has(self::FILTERS_QUERY_PARAM)) {
            // Save: capture the active filters into the session.
            $session->set($sessionKey, $request->query->all(self::FILTERS_QUERY_PARAM));

            return;
        }

        // Reset detection: same-path Referer + no filters in target = user
        // explicitly clicked EA's `action-filters-reset` (or another link
        // that strips filters while keeping the path). Wipe the session
        // slot so the user actually sees an unfiltered list.
        //
        // We also redirect to the canonical path so the modal Clear (which
        // submits the GET form with all filter inputs removed) doesn't
        // leave a trailing `?` or stale `crudAction`/`crudControllerFqcn`
        // params in the URL.
        if ($this->isExplicitReset($request)) {
            $session->remove($sessionKey);

            // Modal Clear submits the GET form with all filter inputs
            // removed — the browser appends a stale `?` (or stray
            // crudAction/crudControllerFqcn params from the form action
            // URL) even when no filters remain, leaving the user staring
            // at `/admin/product?`. Redirect to the canonical path so
            // the URL bar lands clean. The `action-filters-reset` link
            // already produces a bare path; in that case the raw
            // REQUEST_URI matches getPathInfo() and we skip the redirect.
            $requestUri = $request->server->get('REQUEST_URI', '');
            if ($requestUri !== $request->getPathInfo()) {
                $event->setResponse(new RedirectResponse($request->getPathInfo()));
            }

            return;
        }

        // Restore: no filters in the URL — replay the saved ones if any.
        $saved = $session->get($sessionKey);
        if (!\is_array($saved) || [] === $saved) {
            return;
        }

        $separator = str_contains($request->getUri(), '?') ? '&' : '?';
        $query = http_build_query([self::FILTERS_QUERY_PARAM => $saved]);
        $event->setResponse(new RedirectResponse($request->getUri() . $separator . $query));
    }

    /**
     * The user explicitly asked to clear filters when:
     *  - the target URL has no `filters[…]`, AND
     *  - the Referer header is on the same path with `filters[…]` set.
     *
     * Different paths (coming back from edit / detail / dashboard) are
     * NOT a reset — those are exactly the case session restore exists
     * for.
     */
    private function isExplicitReset(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        $referer = $request->headers->get('referer');
        if (null === $referer || '' === $referer) {
            return false;
        }

        $refererPath = parse_url($referer, \PHP_URL_PATH);
        if (null === $refererPath || $refererPath !== $request->getPathInfo()) {
            return false;
        }

        $refererQuery = parse_url($referer, \PHP_URL_QUERY);
        if (null === $refererQuery || '' === $refererQuery) {
            return false;
        }

        // The Referer URL had `filters[…]`, the target doesn't — that's
        // a reset.
        parse_str($refererQuery, $parsed);

        return isset($parsed[self::FILTERS_QUERY_PARAM]);
    }

    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (\Throwable) {
            // No session available (e.g. CLI command, or stateless route)
            // — gracefully no-op rather than crash the whole request.
            return null;
        }
    }
}
