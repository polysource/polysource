<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ComparisonFilterType;
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
        $resolver->setNormalizer('comparisons', static function ($options, array $value): array {
            foreach ($value as $i => $comparison) {
                if (!\is_string($comparison)) {
                    throw new \InvalidArgumentException(
                        \sprintf('comparisons[%d] must be a string operator (e.g. "=", "!="), got %s.', $i, get_debug_type($comparison)),
                    );
                }
            }

            return $value;
        });
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_comparison_filter';
    }
}
