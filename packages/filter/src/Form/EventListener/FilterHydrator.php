<?php

declare(strict_types=1);

namespace Polysource\Filter\Form\EventListener;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Model\FilterDefinition;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hydrates a `FilterCollectionType` form bidirectionally between the
 * immutable `FilterCollection` model and the raw form data array.
 *
 * Both directions go through `FilterMapperInterface` impls (resolved
 * via `MapperRegistry`):
 *
 * 1. **PRE_SET_DATA** (model → form): given a `?FilterCollection`,
 *    walks each definition, finds its existing criterion (if any),
 *    and calls `mapper->toFormData()` to fill the sub-form. Sub-forms
 *    that have no matching criterion stay empty.
 *
 * 2. **PRE_SUBMIT** (form → model): given the raw posted array, walks
 *    each definition, slices the relevant entry, calls
 *    `mapper->fromRequest()`, and assembles a new `FilterCollection`.
 *    The form's data is then set to this collection so callers
 *    `$form->getData()` get a proper model.
 *
 * The collection `id` is taken from the form's `collection_id`
 * option — host-defined (e.g. CRUD controller FQCN, route name).
 *
 * Definitions are picked up from the form's `definitions` option.
 * Each is keyed by its `property` in the form children — that's how
 * the listener correlates form slices with definitions.
 */
final class FilterHydrator implements EventSubscriberInterface
{
    /**
     * @param list<FilterDefinition> $definitions
     */
    public function __construct(
        private readonly MapperRegistry $mappers,
        private readonly array $definitions,
        private readonly string $collectionId,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
            FormEvents::SUBMIT => 'onSubmit',
        ];
    }

    public function onPreSetData(FormEvent $event): void
    {
        $data = $event->getData();
        if (!$data instanceof FilterCollection) {
            // Default to an empty array shape so each child renders
            // empty. Hosts that want to pre-fill pass a FilterCollection.
            $event->setData([]);

            return;
        }

        // Build the form-shape: an associative array keyed by property
        // name, where each entry is the mapper's toFormData() output.
        $formData = [];
        foreach ($this->definitions as $definition) {
            $criterion = $data->get($definition->property);
            if (null === $criterion) {
                $formData[$definition->property] = [];
                continue;
            }

            $mapper = $this->mappers->forName($definition->name);
            $formData[$definition->property] = $mapper->toFormData($criterion);
        }

        $event->setData($formData);
    }

    public function onPreSubmit(FormEvent $event): void
    {
        // Sanity-pass the raw input. Symfony Form will dispatch this
        // sanitised array to each child sub-form. The actual
        // FilterCollection assembly happens in POST_SUBMIT (after
        // children have validated and produced their normalised data).
        $raw = $event->getData();
        if (!\is_array($raw)) {
            $event->setData([]);
        }
    }

    public function onSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $collection = new FilterCollection($this->collectionId);

        foreach ($this->definitions as $definition) {
            if (!$form->has($definition->property)) {
                continue;
            }
            $child = $form->get($definition->property);
            $slice = $child->getData();
            if (!\is_array($slice) || $this->isEmptySlice($slice)) {
                continue;
            }
            /** @var array<string, mixed> $slice */
            $mapper = $this->mappers->forName($definition->name);
            try {
                $criterion = $mapper->fromRequest($definition->property, $slice);
            } catch (\InvalidArgumentException) {
                continue;
            }
            if ([] === $criterion->values) {
                continue;
            }
            $collection = $collection->with($criterion);
        }

        $event->setData($collection);
    }

    /**
     * @param array<int|string, mixed> $slice
     */
    private function isEmptySlice(array $slice): bool
    {
        foreach ($slice as $key => $value) {
            if ('comparison' === $key) {
                continue;
            }
            if (null !== $value && '' !== $value && [] !== $value) {
                return false;
            }
        }

        return true;
    }
}
