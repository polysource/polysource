<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `EntityFilterType`.
 *
 * Inherits the upstream EntityType + comparison fields (with optional
 * autocomplete via `data-ea-widget`). Adds one option:
 *
 * - `placeholder` (string|null): custom placeholder text for the
 *   dropdown / autocomplete (e.g. "Pick a category…"). Default `null`
 *   keeps the upstream behaviour (no placeholder).
 *
 * Block prefix is dedicated (`polysource_enhanced_entity_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedEntityFilterType extends EntityFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'placeholder' => null,
        ]);

        $resolver->setAllowedTypes('placeholder', ['null', 'string']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['placeholder'] = $options['placeholder'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_entity_filter';
    }
}
