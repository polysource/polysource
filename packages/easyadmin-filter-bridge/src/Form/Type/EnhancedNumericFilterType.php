<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;
use InvalidArgumentException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `NumericFilterType`.
 *
 * Inherits the upstream comparison + value + value2 (BETWEEN support)
 * fields. Adds two options:
 *
 * - `step` (int|float ≥ 0): granularity hint for the underlying input
 *   (e.g. `0.01` for currency, `1` for quantities). Default `0` means
 *   "no step", upstream behaviour.
 *
 * - `quick_ranges` (list<array{label: string, min: ?float, max: ?float}>):
 *   one-click presets rendered as buttons next to the value inputs (e.g.
 *   `<100` / `100-1000` / `1000+`). Empty by default — host apps opt in.
 *
 * Block prefix is dedicated (`polysource_enhanced_numeric_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedNumericFilterType extends NumericFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'step' => 0,
            'quick_ranges' => [],
        ]);

        $resolver->setAllowedTypes('step', ['int', 'float']);
        $resolver->setAllowedValues('step', static fn (int|float $v): bool => $v >= 0);

        $resolver->setAllowedTypes('quick_ranges', 'array');
        $resolver->setNormalizer('quick_ranges', static function ($options, array $value): array {
            foreach ($value as $i => $range) {
                if (!\is_array($range) || !\array_key_exists('label', $range)) {
                    throw new InvalidArgumentException(\sprintf('quick_ranges[%d] must be an array with at least a "label" key.', $i));
                }
            }

            return $value;
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['step'] = $options['step'];
        $view->vars['quick_ranges'] = $options['quick_ranges'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_numeric_filter';
    }
}
