<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `NumericFilterType`.
 *
 * Inherits the upstream comparison + value + value2 (BETWEEN support)
 * fields. Adds one option:
 *
 * - `step` (int|float ≥ 0): granularity hint for the underlying input
 *   (e.g. `0.01` for currency, `1` for quantities). Default `0` means
 *   "no step", upstream behaviour.
 *
 * Block prefix is dedicated (`polysource_enhanced_numeric_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 *
 * Earlier versions (v0.1.x) shipped a `quick_ranges` option (one-click
 * preset buttons like `<100` / `100-1000` / `1000+`) driven by a
 * Stimulus controller. It was removed in v0.2.0 because:
 *
 * - It required JS — hosts without a Stimulus pipeline saw inert
 *   buttons (violation of ADR-027 progressive enhancement).
 * - The use case is rare; hosts who need range shortcuts can add
 *   their own buttons in a CRUD-specific template (ADR-028 scope).
 */
final class EnhancedNumericFilterType extends NumericFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'step' => 0,
        ]);

        $resolver->setAllowedTypes('step', ['int', 'float']);
        $resolver->setAllowedValues('step', static fn (int|float $v): bool => $v >= 0);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['step'] = $options['step'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_numeric_filter';
    }
}
