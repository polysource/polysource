<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for {@see \Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter}.
 *
 * Single text input. The hidden `comparison` field carries a fixed `=`
 * sentinel that `FullTextSearchFilter::apply()` never reads — it exists
 * only so EasyAdmin's `FilterDataDto` (whose `$comparison` is a
 * non-nullable `string` since EA 5.5) always receives a string. An empty
 * string CANNOT be used here: Symfony Forms treats '' as "empty", so
 * `empty_data: ''` never applies and the submitted field resolves to
 * null, which fatals on EA 5.5 at the first modal submission.
 * `apply()` reads the configured `properties` list directly from its
 * `FilterDataDto::getFormTypeOption()` and uses LIKE clauses across them.
 */
final class FullTextSearchFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('comparison', HiddenType::class, [
                'data' => ComparisonType::EQ,
                'empty_data' => ComparisonType::EQ,
            ])
            ->add('value', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => $options['placeholder'],
                ],
                // Symfony's form themes translate the `placeholder` attr
                // through the field's translation_domain: the default
                // placeholder is a TRANSLATION KEY in the bridge's domain
                // (2026-08 host report: hardcoded "Search…" in a French
                // UI). Host-provided plain strings pass through unchanged.
                'translation_domain' => 'PolysourceEasyAdminFilterBridge',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'placeholder' => 'polysource.filter.full_text.placeholder',
            'properties' => [],
            'data_class' => null,
            'compound' => true,
            'translation_domain' => 'EasyAdminBundle',
        ]);

        $resolver->setAllowedTypes('placeholder', 'string');
        $resolver->setAllowedTypes('properties', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_full_text_search_filter';
    }
}
