<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Form\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form type for {@see \Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter}.
 *
 * Renders a stripped-down two-date form (a "from" and a "to" picker)
 * without the comparison dropdown — `BetweenDateFilter` always
 * applies a BETWEEN-style range, so exposing the operator selector
 * would be misleading. The hidden `comparison` field carries
 * `ComparisonType::BETWEEN` so the EasyAdmin `FilterDataDto` flow
 * (which always reads `comparison`/`value`/`value2`) stays compatible.
 *
 * Both bounds are optional: if only `value` is set the filter falls
 * back to `>= value`; if only `value2` is set, to `<= value2`. The
 * applier (`BetweenDateFilter::apply()`) is the single source of
 * truth for this fallback.
 */
final class BetweenDateFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var class-string<\Symfony\Component\Form\FormTypeInterface> $valueType */
        $valueType = $options['value_type'];
        /** @var array<string, mixed> $valueTypeOptions */
        $valueTypeOptions = $options['value_type_options'];

        $builder
            ->add('comparison', HiddenType::class, [
                'data' => ComparisonType::BETWEEN,
                'empty_data' => ComparisonType::BETWEEN,
            ])
            ->add('value', $valueType, $valueTypeOptions + ['required' => false, 'label' => false])
            ->add('value2', $valueType, $valueTypeOptions + ['required' => false, 'label' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'value_type' => DateType::class,
            'value_type_options' => [
                'widget' => 'single_text',
                'html5' => true,
            ],
            'data_class' => null,
            'compound' => true,
            'translation_domain' => 'EasyAdminBundle',
        ]);

        $resolver->setAllowedTypes('value_type', 'string');
        $resolver->setAllowedTypes('value_type_options', 'array');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // Surface the value type so a host theme can branch on date
        // vs datetime if needed.
        $view->vars['value_type'] = $options['value_type'];
    }

    public function getBlockPrefix(): string
    {
        return 'polysource_between_date_filter';
    }
}
