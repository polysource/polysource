<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ArrayFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `ArrayFilterType`.
 *
 * Inherits the upstream multi-select choice + comparison fields. Adds
 * one option:
 *
 * - `chip_display` (bool): renders the selected items as chips/tags
 *   each removable individually, instead of the upstream multi-line
 *   list. Default `false` keeps the upstream rendering. Useful for
 *   long array columns (tags, capabilities) where the chip UI is
 *   easier to scan and clear.
 *
 * Block prefix is dedicated (`polysource_enhanced_array_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedArrayFilterType extends ArrayFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'chip_display' => false,
        ]);

        $resolver->setAllowedTypes('chip_display', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['chip_display'] = $options['chip_display'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_array_filter';
    }
}
