<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Enhanced replacement for EasyAdmin's `BooleanFilterType`.
 *
 * Inherits the upstream radio choice (`label.true` / `label.false`) and
 * adds an optional `include_null` flag — when true, a third "Empty / Null"
 * choice appears so operators can filter rows where the column is NULL.
 * Nullable booleans are common in real schemas (`is_archived?`,
 * `email_verified_at?`, `consent_given?`) and the upstream filter has no
 * way to target them.
 *
 * Block prefix is dedicated (`polysource_enhanced_boolean_filter`) so a
 * host application can override the rendering with a custom form theme
 * without affecting the built-in EasyAdmin filter.
 */
final class EnhancedBooleanFilterType extends BooleanFilterType
{
    public const CHOICE_TRUE = 'label.true';
    public const CHOICE_FALSE = 'label.false';
    public const CHOICE_NULL = 'label.null';

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'include_null' => false,
        ]);

        $resolver->setAllowedTypes('include_null', 'bool');

        // When include_null is true, normalize the 'choices' default to
        // include the third option. This way the host app can also pass
        // a custom 'choices' map; we only auto-extend the upstream default.
        $resolver->setNormalizer('choices', static function ($options, $value) {
            $choices = \is_array($value) ? $value : [
                self::CHOICE_TRUE => true,
                self::CHOICE_FALSE => false,
            ];

            if (true === $options['include_null'] && !\array_key_exists(self::CHOICE_NULL, $choices)) {
                $choices[self::CHOICE_NULL] = null;
            }

            return $choices;
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars['include_null'] = $options['include_null'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_enhanced_boolean_filter';
    }
}
