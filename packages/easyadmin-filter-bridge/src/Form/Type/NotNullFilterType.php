<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for {@see \Polysource\EasyAdminFilterBridge\Filter\NotNullFilter}.
 *
 * Tri-state radio: "Any" (no filter), "Has value" (IS NOT NULL),
 * "Empty" (IS NULL). The hidden `comparison` field is left empty
 * — `NotNullFilter::apply()` reads `value` (the radio's choice
 * key) directly. Field labels can be customised via the `labels`
 * option which is forwarded to the inner ChoiceType.
 */
final class NotNullFilterType extends AbstractType
{
    public const VALUE_ANY = '';
    public const VALUE_NOT_NULL = 'not_null';
    public const VALUE_NULL = 'null';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array{any: string, not_null: string, null: string} $labels */
        $labels = $options['labels'];
        $choices = [
            $labels['any'] => self::VALUE_ANY,
            $labels['not_null'] => self::VALUE_NOT_NULL,
            $labels['null'] => self::VALUE_NULL,
        ];

        $builder
            ->add('comparison', HiddenType::class, [
                'data' => '',
                'empty_data' => '',
            ])
            ->add('value', ChoiceType::class, [
                'choices' => $choices,
                'expanded' => $options['expanded'],
                'multiple' => false,
                'required' => false,
                'label' => false,
                'placeholder' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'labels' => [
                'any' => 'Any',
                'not_null' => 'Has value',
                'null' => 'Empty',
            ],
            'expanded' => true,
            'data_class' => null,
            'compound' => true,
            'translation_domain' => 'EasyAdminBundle',
        ]);

        $resolver->setAllowedTypes('labels', 'array');
        $resolver->setAllowedTypes('expanded', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_not_null_filter';
    }
}
