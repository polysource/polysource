<?php

declare(strict_types=1);

namespace Polysource\Filter\Form\Type;

use InvalidArgumentException;
use Polysource\Filter\Form\EventListener\FilterHydrator;
use Polysource\Filter\Model\FilterDefinition;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Compound form for a `FilterCollection` — one sub-form per
 * `FilterDefinition` declared in the `definitions` option.
 *
 * The sub-form FormType for each definition is resolved via the
 * pipeline (`RendererRegistry::forName($name)::getFormType()`) unless
 * the definition's `formSpec` carries an explicit `form_type` key.
 *
 * Hydration (model ↔ form data) is done by `FilterHydrator`:
 * - PRE_SET_DATA: feeds raw arrays per sub-form so each child renders.
 * - PRE_SUBMIT: builds a fresh `FilterCollection` from the posted data
 *   and stashes it in the form's `polysource_filter_collection`
 *   attribute. POST_SUBMIT then promotes that attribute to the form's
 *   normData (so $form->getData() returns the collection).
 *
 * Two required options:
 * - `collection_id` (string)            — scope identifier for the
 *   FilterService session slot.
 * - `definitions`   (list<FilterDefinition>) — the available filters.
 *
 * Optional:
 * - `mode`          ('integrated'|'subpanel') — passed through to
 *   form.vars so the rendering theme can pick the right template.
 *   Defaults to 'integrated'.
 */
final class FilterCollectionType extends AbstractType
{
    public function __construct(
        private readonly MapperRegistry $mappers,
        private readonly RendererRegistry $renderers,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<FilterDefinition> $definitions */
        $definitions = $options['definitions'];
        /** @var string $collectionId */
        $collectionId = $options['collection_id'];

        foreach ($definitions as $definition) {
            $formType = $this->resolveFormType($definition);
            $childOptions = $this->resolveChildOptions($definition);
            /** @var class-string<FormTypeInterface> $formType */
            $builder->add($definition->property, $formType, $childOptions);
        }

        // Hydrator handles all 3 phases (PRE_SET_DATA model→form,
        // PRE_SUBMIT sanitisation, POST_SUBMIT form→FilterCollection
        // promoted to the form's normData).
        $builder->addEventSubscriber(new FilterHydrator(
            $this->mappers,
            $definitions,
            $collectionId,
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        /** @var list<FilterDefinition> $definitions */
        $definitions = $options['definitions'];
        $view->vars['polysource_filter_definitions'] = $definitions;
        $view->vars['polysource_filter_mode'] = $options['mode'];
        $view->vars['polysource_filter_collection_id'] = $options['collection_id'];

        // Group the definitions by their `group` for multi-group
        // rendering (subpanel tabs / integrated accordion sections).
        $groups = [];
        foreach ($definitions as $definition) {
            $key = $definition->group ?? '';
            $groups[$key] ??= [];
            $groups[$key][] = $definition;
        }
        $view->vars['polysource_filter_groups'] = $groups;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mode' => 'integrated',
            'data_class' => null,
            'compound' => true,
            'allow_extra_fields' => true,
        ]);

        $resolver->setRequired(['collection_id', 'definitions']);

        $resolver->setAllowedTypes('collection_id', 'string');
        $resolver->setAllowedTypes('definitions', 'array');
        $resolver->setAllowedTypes('mode', 'string');
        $resolver->setAllowedValues('mode', ['integrated', 'subpanel']);

        $resolver->setNormalizer('definitions', static function ($options, mixed $value): array {
            if (!\is_array($value)) {
                throw new InvalidArgumentException('definitions must be an array of FilterDefinition.');
            }
            $list = [];
            foreach ($value as $i => $entry) {
                if (!$entry instanceof FilterDefinition) {
                    throw new InvalidArgumentException(\sprintf('definitions[%s] must be a FilterDefinition, got %s.', (string) $i, get_debug_type($entry)));
                }
                $list[] = $entry;
            }

            return $list;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_filter_collection';
    }

    /**
     * @return class-string
     */
    private function resolveFormType(FilterDefinition $definition): string
    {
        // Host-side override via formSpec wins.
        if (isset($definition->formSpec['form_type']) && \is_string($definition->formSpec['form_type'])) {
            /** @var class-string $formType */
            $formType = $definition->formSpec['form_type'];

            return $formType;
        }

        return $this->renderers->forName($definition->name)->getFormType();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveChildOptions(FilterDefinition $definition): array
    {
        $options = $definition->formSpec;
        // Strip the `form_type` key — it's our routing hint, not a
        // FormType option.
        unset($options['form_type']);

        // The label defaults to the FilterDefinition's label, then to
        // the property as a fallback.
        $options['label'] ??= '' !== $definition->label ? $definition->label : $definition->property;

        // Sub-forms must be optional (not required) — when a user
        // doesn't fill a filter, the form must validate.
        $options['required'] ??= false;
        $options['mapped'] = false;

        return $options;
    }
}
