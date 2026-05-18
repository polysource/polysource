<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Polysource\Filter\SavedView\SavedViewApplyService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
        private readonly SavedViewApplyService $applyService,
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

        $context = $event->getAdminContext();
        $crud = $context?->getCrud();
        if ($crud === null) {
            return;
        }

        // Load + resource-check delegated to the shared service. The
        // resource is keyed by EA's entity FQCN for EA-bridge mode.
        $view = $this->applyService->resolveView(
            (string) $request->query->get('view', ''),
            $crud->getEntityFqcn(),
        );
        if (null === $view) {
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

        $event->setResponse($this->applyService->buildRedirect($request->getPathInfo(), $merged));
    }

    /**
     * Translate a Polysource FilterCollection back to EA's URL shape.
     * The shape DEPENDS on each filter's FormType:
     *
     * - Filters whose FormType is `BooleanFilterType` (a Symfony
     *   ChoiceType, no comparison/value envelope) → bare scalar:
     *   `filters[<property>]=<scalar>`.
     * - Filters whose FormType has `multiple: true` (ChoiceFilter /
     *   EntityFilter / ArrayFilter with `canSelectMultiple`) → envelope
     *   with array value: `filters[<p>][value][]=v` even for `eq`. The
     *   underlying ChoiceType refuses scalars when `multiple` is set —
     *   it silently coerces them to an empty selection and the table
     *   shows unfiltered. Pre-v0.1.0 showcase bug: a saved view created
     *   from a single-item ChoiceFilter selection saved as `eq` would
     *   replay as `value=paid` scalar and the table never filtered;
     *   the user perceived "first click does nothing".
     * - Every other FormType (ComparisonFilter-derived: text, numeric,
     *   datetime, single-value choice/entity, ...) → envelope:
     *   `filters[<p>][comparison]=<op>&[value]=<v>` (scalar value).
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

            $expectsArrayValue = $this->filterExpectsArrayValue($filtersConfig, $property);

            // Single-value scalar wrapped to a 1-element list when the form expects array.
            $singleValue = $expectsArrayValue
                ? [$values[0] ?? '']
                : ($values[0] ?? '');

            $entry = match ($criterion->operator) {
                'eq' => ['comparison' => '=', 'value' => $singleValue],
                'neq' => ['comparison' => '!=', 'value' => $singleValue],
                'gt' => ['comparison' => '>', 'value' => $singleValue],
                'gte' => ['comparison' => '>=', 'value' => $singleValue],
                'lt' => ['comparison' => '<', 'value' => $singleValue],
                'lte' => ['comparison' => '<=', 'value' => $singleValue],
                'like' => ['comparison' => 'like', 'value' => $singleValue],
                'in' => ['comparison' => '=', 'value' => array_values($values)],
                'between' => [
                    'comparison' => 'between',
                    'value' => ['min' => $values[0] ?? '', 'max' => $values[1] ?? ''],
                ],
                default => ['comparison' => $criterion->operator, 'value' => $singleValue],
            };

            $filters[$property] = $entry;
        }

        return $filters !== [] ? ['filters' => $filters] : [];
    }

    /**
     * True when the property's filter declared a multi-select FormType
     * (ChoiceType / EntityType with `multiple: true`). Single-element
     * selections must replay as `value[]=x` arrays, never `value=x`
     * scalars — the form's choice transformer silently drops scalar
     * input on `multiple: true` types.
     */
    private function filterExpectsArrayValue(
        \EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto $filtersConfig,
        string $property,
    ): bool {
        $declared = $filtersConfig->getFilter($property);
        if (null === $declared || \is_string($declared)) {
            return false;
        }

        $dto = $declared->getAsDto();
        $options = $dto->getFormTypeOptions();

        // ChoiceFilter / EntityFilter expose `canSelectMultiple()` via the
        // nested `value_type_options.multiple` form-type option. Some
        // filters set `multiple` at the top level instead — accept both.
        $valueOptions = $options['value_type_options'] ?? null;
        if (\is_array($valueOptions) && true === ($valueOptions['multiple'] ?? null)) {
            return true;
        }

        return true === ($options['multiple'] ?? null);
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
