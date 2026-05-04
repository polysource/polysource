<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for {@see \Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter}.
 *
 * Single text input. The hidden `comparison` field is left empty —
 * `FullTextSearchFilter::apply()` reads the configured `properties`
 * list directly from its `FilterDataDto::getFormTypeOption()` and
 * uses LIKE clauses across them.
 */
final class FullTextSearchFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('comparison', HiddenType::class, [
                'data' => '',
                'empty_data' => '',
            ])
            ->add('value', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    'placeholder' => $options['placeholder'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'placeholder' => 'Search…',
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
