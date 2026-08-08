<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for {@see \Polysource\EasyAdminFilterBridge\Filter\NotNullFilter}.
 *
 * Tri-state radio: "Any" (no filter), "Has value" (IS NOT NULL),
 * "Empty" (IS NULL). The hidden `comparison` field carries a fixed `=`
 * sentinel that `NotNullFilter::apply()` never reads (it reads `value`,
 * the radio's choice key) — it exists only so EasyAdmin's
 * `FilterDataDto` (non-nullable `string $comparison` since EA 5.5)
 * always receives a string. An empty string CANNOT be used here:
 * Symfony Forms treats '' as "empty", so `empty_data: ''` never applies
 * and the field resolves to null, which fatals on EA 5.5. Field labels
 * can be customised via the `labels` option which is forwarded to the
 * inner ChoiceType.
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
                'data' => ComparisonType::EQ,
                'empty_data' => ComparisonType::EQ,
            ])
            ->add('value', ChoiceType::class, [
                'choices' => $choices,
                'expanded' => $options['expanded'],
                'multiple' => false,
                'required' => false,
                'label' => false,
                'placeholder' => false,
                // The default labels are TRANSLATION KEYS resolved in the
                // bridge's own domain (2026-08 host report: hardcoded
                // English tri-state in a French UI). Host-provided plain
                // strings pass through unchanged — unknown keys are
                // echoed verbatim by the translator.
                'choice_translation_domain' => 'PolysourceEasyAdminFilterBridge',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'labels' => [
                'any' => 'polysource.filter.not_null.any',
                'not_null' => 'polysource.filter.not_null.has_value',
                'null' => 'polysource.filter.not_null.empty',
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
