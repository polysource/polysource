<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Service\FilterService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Persists filter values in the HTTP session, scoped per CRUD controller
 * FQCN, so an operator returning to the index page after navigating away
 * sees their previous filters restored automatically.
 *
 * Storage delegates to `polysource/filter`'s `FilterService` — the
 * subscriber translates EasyAdmin's URL-based filter shape
 * (`?filters[<property>][comparison|value|value2]=…`) into a
 * `FilterCollection` and back. The translation is mechanical: each
 * URL property slot becomes one `FilterCriterion`.
 *
 * Behaviour, on every `BeforeCrudActionEvent` for the INDEX action:
 *
 * 1. **Save**: if the request URL carries a `filters[…]` query parameter,
 *    convert it to a `FilterCollection` and persist via FilterService.
 *
 * 2. **Reset detection**: same-path Referer with filters set + no
 *    filters in target URL = explicit reset → `FilterService::clear()`.
 *    Modal Clear's trailing `?` is also normalised by redirecting to
 *    the canonical path.
 *
 * 3. **Restore**: no filters in URL AND a saved collection exists →
 *    redirect to the same URL with the collection re-encoded as
 *    `filters[…]=…` query string.
 *
 * No effect on non-INDEX actions.
 */
final class FilterSessionPersistenceSubscriber implements EventSubscriberInterface
{
    private const FILTERS_QUERY_PARAM = 'filters';

    public function __construct(private readonly FilterService $filterService)
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
        // Yield to any earlier subscriber that already produced a
        // response (typically SavedViewApplySubscriber on `?view=<id>`
        // clicks). EasyAdmin's BeforeCrudActionEvent uses its own
        // StoppableEventTrait — Symfony's EventDispatcher only checks
        // PSR-14 StoppableEventInterface, so listeners on this event
        // are NOT auto-skipped after a setResponse(). Without this
        // guard, the explicit-reset branch below would override the
        // saved-view redirect with a clean `/admin/order` URL when the
        // user switches between two saved views (the new request has
        // no `filters` but the Referer does, triggering reset
        // detection). Pre-v0.1.0 showcase bug.
        if ($event->isPropagationStopped()) {
            return;
        }

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

        $request = $context->getRequest();

        // Saved-view apply round-trip: the user clicked a saved view
        // in the dropdown. The earlier SavedViewApplySubscriber will
        // redirect to the canonical `?filters[...]` URL; this subscriber
        // must not concurrently treat the missing `filters` param as
        // an explicit reset (which would clear session state AND
        // override the redirect with a clean URL).
        if ($request->query->has('view')) {
            return;
        }

        if ($request->query->has(self::FILTERS_QUERY_PARAM)) {
            /** @var array<string, mixed> $rawFilters */
            $rawFilters = $request->query->all(self::FILTERS_QUERY_PARAM);
            $collection = $this->collectionFromUrlFilters($controllerFqcn, $rawFilters);
            if (!$collection->isEmpty()) {
                $this->filterService->save($collection);
            }

            return;
        }

        if ($this->isExplicitReset($request)) {
            $this->filterService->clear($controllerFqcn);

            // Canonicalise the post-reset URL: drop the `filters` slice
            // and the trailing '?' the modal Clear leaves behind, but
            // PRESERVE every other query parameter — hosts scope their
            // CRUDs with context params (e.g. `?parentId=42`) and a
            // redirect to the bare path silently drops that context
            // (surfaced 2026-08 by a host whose member listing 404s
            // without its group id).
            $remaining = $request->query->all();
            unset($remaining[self::FILTERS_QUERY_PARAM]);
            $canonicalUri = $request->getPathInfo()
                . ([] === $remaining ? '' : '?' . http_build_query($remaining));

            $requestUri = $request->server->get('REQUEST_URI', '');
            if ($requestUri !== $canonicalUri) {
                $event->setResponse(new RedirectResponse($canonicalUri));
            }

            return;
        }

        // Restore: no filters in URL — load the saved collection.
        $saved = $this->filterService->load($controllerFqcn);
        if (null === $saved || $saved->isEmpty()) {
            return;
        }

        $urlFilters = $this->urlFiltersFromCollection($saved);
        $separator = str_contains($request->getUri(), '?') ? '&' : '?';
        $query = http_build_query([self::FILTERS_QUERY_PARAM => $urlFilters]);
        $event->setResponse(new RedirectResponse($request->getUri() . $separator . $query));
    }

    /**
     * Translate the URL filter shape into a `FilterCollection`.
     *
     * Convention: each `filters[<property>]` slice becomes one
     * `FilterCriterion(property, operator=comparison, values=[value, value2?])`.
     * Empty slices (no value AND no value2) are dropped.
     *
     * @param array<string, mixed> $rawFilters
     */
    private function collectionFromUrlFilters(string $id, array $rawFilters): FilterCollection
    {
        $collection = new FilterCollection($id);
        foreach ($rawFilters as $property => $slice) {
            if (!\is_string($property) || '' === $property) {
                continue;
            }
            if (!\is_array($slice)) {
                // Bare scalar slice — treat as a single value with `=`.
                if (null === $slice || '' === $slice) {
                    continue;
                }
                $collection = $collection->with(new FilterCriterion($property, '=', [$slice]));
                continue;
            }

            $operator = $slice['comparison'] ?? null;
            if (!\is_string($operator) || '' === $operator) {
                $operator = '=';
            }

            // Only keep scalar value/value2 — a malformed or hostile
            // request could send `?filters[name][value][]=foo` which
            // would arrive as an array. Forcing scalars keeps the
            // criterion roundtrippable (http_build_query would
            // serialise arrays in a different shape than the input)
            // and prevents accidental data corruption when restoring.
            $values = [];
            if (isset($slice['value']) && \is_scalar($slice['value']) && '' !== $slice['value']) {
                $values[] = (string) $slice['value'];
            }
            if (isset($slice['value2']) && \is_scalar($slice['value2']) && '' !== $slice['value2']) {
                $values[] = (string) $slice['value2'];
            }

            if ([] === $values) {
                continue;
            }

            $collection = $collection->with(new FilterCriterion($property, $operator, $values));
        }

        return $collection;
    }

    /**
     * Inverse of `collectionFromUrlFilters()` — produce the URL
     * filters array shape from a stored `FilterCollection`.
     *
     * @return array<string, array<string, mixed>>
     */
    private function urlFiltersFromCollection(FilterCollection $collection): array
    {
        $urlFilters = [];
        foreach ($collection as $criterion) {
            $slice = ['comparison' => $criterion->operator];
            if (isset($criterion->values[0])) {
                $slice['value'] = $criterion->values[0];
            }
            if (isset($criterion->values[1])) {
                $slice['value2'] = $criterion->values[1];
            }
            $urlFilters[$criterion->property] = $slice;
        }

        return $urlFilters;
    }

    private function isExplicitReset(Request $request): bool
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
        if (!\is_string($refererQuery) || '' === $refererQuery) {
            return false;
        }

        parse_str($refererQuery, $parsed);

        return isset($parsed[self::FILTERS_QUERY_PARAM]);
    }
}
