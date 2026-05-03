<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ComparisonFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `ComparisonFilterType`.
 *
 * Inherits the upstream comparison + value structure. Adds one option:
 *
 * - `comparisons` (list<string>): whitelist of comparison operators to
 *   expose in the dropdown (e.g. `['=', '!=']` to hide range/comparison
 *   operators when they are nonsensical for the column). Default `[]`
 *   means "expose every operator the upstream ComparisonType knows" —
 *   non-breaking.
 *
 * Implementation:
 * - The whitelist is applied via Symfony's `choice_filter` option on
 *   the inner `comparison_type_options`, which restricts the rendered
 *   `<select>` choices without us having to re-declare them.
 * - `comparisons` is also exposed in `form.vars` so the widget block
 *   can serialise it as a `data-comparisons` attribute for downstream
 *   JS consumers.
 *
 * Block prefix is dedicated (`polysource_enhanced_comparison_filter`)
 * so a host application can override the rendering with a custom form
 * theme without affecting the built-in EasyAdmin filter.
 */
final class EnhancedComparisonFilterType extends ComparisonFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'comparisons' => [],
        ]);

        $resolver->setAllowedTypes('comparisons', 'array');
        $resolver->setNormalizer('comparisons', static function (Options $options, array $value): array {
            foreach ($value as $i => $comparison) {
                if (!\is_string($comparison)) {
                    throw new \InvalidArgumentException(
                        \sprintf('comparisons[%d] must be a string operator (e.g. "=", "!="), got %s.', $i, get_debug_type($comparison)),
                    );
                }
            }

            return $value;
        });

        // Apply the whitelist by injecting a `choice_filter` callback
        // into the inner ComparisonType — Symfony's ChoiceType drops
        // any option whose value the filter rejects.
        $resolver->setNormalizer('comparison_type_options', static function (Options $options, array $value): array {
            $whitelist = $options['comparisons'];
            if ([] === $whitelist) {
                return $value;
            }

            $value['choice_filter'] = static fn (mixed $choiceValue): bool => \in_array($choiceValue, $whitelist, true);

            return $value;
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['comparisons'] = $options['comparisons'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_comparison_filter';
    }
}
