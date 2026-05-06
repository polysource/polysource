<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Listens for `?view=<id>` on the EasyAdmin index URL and applies the
 * saved view's filters by rewriting the request query before EA's
 * filter pipeline reads it.
 *
 * Without this subscriber, clicking a saved view in the dropdown
 * lands on `/admin/order?view=<id>` and EA simply ignores the unknown
 * `view` query param — the user's filters are not applied.
 *
 * Strategy:
 *   1. On BeforeCrudActionEvent, check for `view` query param.
 *   2. Resolve it via SavedViewService::load().
 *   3. Verify the view's resourceName matches the current entity FQCN
 *      (so a stolen / mismatched id can't redirect across resources).
 *   4. Build the EA-shape `filters[...]=...` query, redirect to that
 *      URL (drops the `view` param). One round-trip = clean URL the
 *      user can bookmark, share, save again.
 *
 * @since 0.1.0
 */
final class SavedViewApplySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SavedViewService $service,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCrudActionEvent::class => ['onBeforeCrudAction', 100],
        ];
    }

    public function onBeforeCrudAction(BeforeCrudActionEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return;
        }

        $viewId = (string) $request->query->get('view', '');
        if ($viewId === '') {
            return;
        }

        $view = $this->service->load($viewId);
        if ($view === null) {
            return;
        }

        $context = $event->getAdminContext();
        $crud = $context?->getCrud();
        if ($crud === null) {
            return;
        }

        $entityFqcn = $crud->getEntityFqcn();
        if ($view->resourceName !== $entityFqcn) {
            // Saved view doesn't belong to this resource — drop silently.
            return;
        }

        $newQuery = self::criteriaToEaQuery($view->filters);
        if ($newQuery === []) {
            return;
        }

        // Preserve other query params except `view`.
        $existing = $request->query->all();
        unset($existing['view']);
        $merged = array_replace_recursive($existing, $newQuery);

        $url = $request->getPathInfo() . '?' . http_build_query($merged);
        $event->setResponse(new RedirectResponse($url));
    }

    /**
     * Translate a Polysource FilterCollection back to EA's URL shape:
     *   filters[<property>][comparison]=<op>
     *   filters[<property>][value]=<scalar>|<list>
     *
     * @return array{}|array{filters: non-empty-array<string, array{comparison: string, value: mixed}>}
     */
    private static function criteriaToEaQuery(\Polysource\Filter\Model\FilterCollection $collection): array
    {
        $filters = [];

        foreach ($collection as $criterion) {
            $property = $criterion->property;
            $values = $criterion->values;

            $entry = match ($criterion->operator) {
                'eq' => ['comparison' => '=', 'value' => $values[0] ?? ''],
                'neq' => ['comparison' => '!=', 'value' => $values[0] ?? ''],
                'gt' => ['comparison' => '>', 'value' => $values[0] ?? ''],
                'gte' => ['comparison' => '>=', 'value' => $values[0] ?? ''],
                'lt' => ['comparison' => '<', 'value' => $values[0] ?? ''],
                'lte' => ['comparison' => '<=', 'value' => $values[0] ?? ''],
                'like' => ['comparison' => 'like', 'value' => $values[0] ?? ''],
                'in' => ['comparison' => 'in', 'value' => array_values($values)],
                'between' => [
                    'comparison' => 'between',
                    'value' => ['min' => $values[0] ?? '', 'max' => $values[1] ?? ''],
                ],
                default => ['comparison' => $criterion->operator, 'value' => $values[0] ?? ''],
            };

            $filters[$property] = $entry;
        }

        return $filters !== [] ? ['filters' => $filters] : [];
    }
}
