<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for {@see \Polysource\EasyAdminFilterBridge\Filter\InFilter}.
 *
 * Renders a multi-select choice picker. The hidden `comparison` field
 * carries `'IN'` (or `'NOT IN'` if the filter is configured with
 * `negate => true`) so EasyAdmin's `FilterDataDto::getComparison()`
 * receives a stable value the applier can branch on.
 */
final class InFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $comparison = true === $options['negate'] ? 'NOT IN' : 'IN';

        $builder
            ->add('comparison', HiddenType::class, [
                'data' => $comparison,
                'empty_data' => $comparison,
            ])
            ->add('value', ChoiceType::class, [
                'choices' => $options['choices'],
                'multiple' => true,
                'expanded' => $options['expanded'],
                'required' => false,
                'label' => false,
                'placeholder' => $options['placeholder'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'choices' => [],
            'negate' => false,
            'expanded' => false,
            'placeholder' => null,
            'data_class' => null,
            'compound' => true,
            'translation_domain' => 'EasyAdminBundle',
        ]);

        $resolver->setAllowedTypes('choices', 'array');
        $resolver->setAllowedTypes('negate', 'bool');
        $resolver->setAllowedTypes('expanded', 'bool');
        $resolver->setAllowedTypes('placeholder', ['null', 'string', 'bool']);
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_in_filter';
    }
}
