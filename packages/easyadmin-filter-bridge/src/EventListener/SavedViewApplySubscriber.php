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

        $newQuery = $this->criteriaToEaQuery($view->filters, $crud->getFiltersConfig());
        if ($newQuery === []) {
            return;
        }

        // Preserve other query params except `view` and the dropdown's
        // `_t` cache-buster (opaque token added by the dropdown template
        // to defeat stale browser caches; it must NOT survive into the
        // canonical filtered URL or the user can't bookmark/share it
        // cleanly).
        $existing = $request->query->all();
        unset($existing['view'], $existing['_t']);
        $merged = array_replace_recursive($existing, $newQuery);

        $url = $request->getPathInfo() . '?' . http_build_query($merged);
        $response = new RedirectResponse($url);
        // Defensive: forbid caching of this 302. Browsers (and any
        // intermediate caches) MUST revalidate. Without this, a stale
        // cached 200 of `?view=<id>` from a prior session — back when
        // the listener didn't exist or didn't redirect — can shadow
        // the live redirect and the user perceives "first click does
        // nothing" until the cache expires. Pre-v0.1.0 demo bug.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $event->setResponse($response);
    }

    /**
     * Translate a Polysource FilterCollection back to EA's URL shape.
     * The shape DEPENDS on each filter's FormType:
     *
     * - Filters whose FormType is `BooleanFilterType` (a Symfony
     *   ChoiceType, no comparison/value envelope) → bare scalar:
     *   `filters[<property>]=<scalar>`.
     * - Every other FormType (ComparisonFilterType-derived: text,
     *   numeric, datetime, choice with multiple, ...) → envelope:
     *   `filters[<property>][comparison]=<op>&[value]=<v>`.
     *
     * Without this dispatch, replaying a saved BooleanFilter view
     * would emit the envelope shape that the underlying ChoiceType
     * doesn't understand → form binding silently drops the slice
     * → no filter applied → table shows wrong rows even though the
     * chips bar renders correctly (chips read the URL verbatim).
     *
     * @return array{}|array{filters: non-empty-array<string, mixed>}
     */
    private function criteriaToEaQuery(
        \Polysource\Filter\Model\FilterCollection $collection,
        \EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto $filtersConfig,
    ): array {
        $filters = [];

        foreach ($collection as $criterion) {
            $property = $criterion->property;
            $values = $criterion->values;

            if ($this->filterUsesBareScalarShape($filtersConfig, $property)) {
                $first = $values[0] ?? '';
                $filters[$property] = \is_scalar($first) ? (string) $first : '';
                continue;
            }

            $entry = match ($criterion->operator) {
                'eq' => ['comparison' => '=', 'value' => $values[0] ?? ''],
                'neq' => ['comparison' => '!=', 'value' => $values[0] ?? ''],
                'gt' => ['comparison' => '>', 'value' => $values[0] ?? ''],
                'gte' => ['comparison' => '>=', 'value' => $values[0] ?? ''],
                'lt' => ['comparison' => '<', 'value' => $values[0] ?? ''],
                'lte' => ['comparison' => '<=', 'value' => $values[0] ?? ''],
                'like' => ['comparison' => 'like', 'value' => $values[0] ?? ''],
                'in' => ['comparison' => '=', 'value' => array_values($values)],
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

    /**
     * Probe the FilterConfigDto for the FormType registered against
     * the given property. Returns true when that FormType is
     * `BooleanFilterType` (or any subclass) — those don't use the
     * comparison/value envelope.
     */
    private function filterUsesBareScalarShape(
        \EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto $filtersConfig,
        string $property,
    ): bool {
        $declared = $filtersConfig->getFilter($property);
        if (null === $declared) {
            return false;
        }

        // FilterInterface objects expose getFormType() via
        // getAsDto(); strings (bare property name registered via
        // `->add('foo')`) don't and resolve to nothing.
        if (\is_string($declared)) {
            return false;
        }

        $formType = $declared->getAsDto()->getFormType();
        if (null === $formType) {
            return false;
        }

        return is_a(
            $formType,
            \EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType::class,
            allow_string: true,
        );
    }
}
