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
 * 2. **Restore**: if the request URL carries no `filters` query param
 *    AND the session has saved filters for this CRUD, redirect to the
 *    same URL with the saved filters appended as `?filters[…]=…`.
 *    `BeforeCrudActionEvent::setResponse()` short-circuits the controller
 *    and returns the redirect immediately.
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

        // Restore: no filters in the URL — replay the saved ones if any.
        $saved = $session->get($sessionKey);
        if (!\is_array($saved) || [] === $saved) {
            return;
        }

        $separator = str_contains($request->getUri(), '?') ? '&' : '?';
        $query = http_build_query([self::FILTERS_QUERY_PARAM => $saved]);
        $event->setResponse(new RedirectResponse($request->getUri() . $separator . $query));
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
