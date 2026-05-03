<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `ChoiceFilterType`.
 *
 * Inherits the upstream comparison + value field (with multiple,
 * expanded, autocomplete via `data-ea-widget` already supported).
 * Adds one option:
 *
 * - `inline` (bool): renders the choices as inline pills/badges next
 *   to the column header instead of a dropdown. Default `false` keeps
 *   the upstream rendering. Useful for short choice sets (≤ 5 options)
 *   where a dropdown is a wasted click.
 *
 * Block prefix is dedicated (`polysource_enhanced_choice_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedChoiceFilterType extends ChoiceFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'inline' => false,
        ]);

        $resolver->setAllowedTypes('inline', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['inline'] = $options['inline'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_choice_filter';
    }
}
