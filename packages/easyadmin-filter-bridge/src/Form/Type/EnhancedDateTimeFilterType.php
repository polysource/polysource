<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\DateTimeFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `DateTimeFilterType`.
 *
 * Inherits the comparison + value + value2 fields from upstream
 * (`ea_datetime_filter`), and adds an optional `presets` option which the
 * Twig theme renders as one-click buttons (Today / Last 7 days / Last 30
 * days / This month / Custom range).
 *
 * Block prefix is dedicated (`polysource_enhanced_datetime_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedDateTimeFilterType extends DateTimeFilterType
{
    public const PRESET_TODAY = 'today';
    public const PRESET_LAST_7_DAYS = 'last_7_days';
    public const PRESET_LAST_30_DAYS = 'last_30_days';
    public const PRESET_THIS_MONTH = 'this_month';
    public const PRESET_CUSTOM = 'custom';

    /** @var list<string> */
    public const DEFAULT_PRESETS = [
        self::PRESET_TODAY,
        self::PRESET_LAST_7_DAYS,
        self::PRESET_LAST_30_DAYS,
        self::PRESET_THIS_MONTH,
        self::PRESET_CUSTOM,
    ];

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'presets' => self::DEFAULT_PRESETS,
            'show_clear' => true,
        ]);

        $resolver->setAllowedTypes('presets', 'array');
        $resolver->setAllowedTypes('show_clear', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['presets'] = $options['presets'];
        $view->vars['show_clear'] = $options['show_clear'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_datetime_filter';
    }
}
