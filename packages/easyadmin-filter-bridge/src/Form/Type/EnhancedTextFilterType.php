<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `TextFilterType`.
 *
 * Inherits the upstream comparison + value fields and adds an optional
 * `min_length` flag — when set, the front-end / hydrator skips the
 * filter for input shorter than the threshold. Real-world admins often
 * have noisy text columns (descriptions, notes); a 1-character search
 * triggers a full-table scan that returns thousands of irrelevant
 * matches. Setting `min_length: 3` is the simplest fix.
 *
 * Block prefix is dedicated (`polysource_enhanced_text_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedTextFilterType extends TextFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'min_length' => 0,
        ]);

        $resolver->setAllowedTypes('min_length', 'int');
        $resolver->setAllowedValues('min_length', static fn (int $v): bool => $v >= 0);
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_text_filter';
    }
}
