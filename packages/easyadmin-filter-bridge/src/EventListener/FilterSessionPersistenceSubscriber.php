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

            $requestUri = $request->server->get('REQUEST_URI', '');
            if ($requestUri !== $request->getPathInfo()) {
                $event->setResponse(new RedirectResponse($request->getPathInfo()));
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

            $values = [];
            if (isset($slice['value']) && '' !== $slice['value']) {
                $values[] = $slice['value'];
            }
            if (isset($slice['value2']) && '' !== $slice['value2']) {
                $values[] = $slice['value2'];
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
