<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Form\Type;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Form\Type\BetweenDateFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedTextFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\FullTextSearchFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\InFilterType;
use Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;

/**
 * Pin the public contract of every Enhanced*FilterType the bridge
 * ships:
 *  - dedicated `getBlockPrefix()` so hosts can override per-type
 *    Twig form theme blocks
 *  - extra options (presets, quick_ranges, min_length, …) declared
 *    with their default values + validation rules
 *  - `buildView()` exposes those options to the Twig theme via
 *    `$view->vars`
 *
 * Tests are stub-driven (no real Symfony form factory needed) — we
 * exercise `configureOptions()` and `buildView()` directly.
 */
final class EnhancedFilterTypesTest extends TestCase
{
    public function testEnhancedTextFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_text_filter', (new EnhancedTextFilterType())->getBlockPrefix());
    }

    public function testEnhancedTextFilterTypeMinLengthDefaultsToZero(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedTextFilterType())->configureOptions($resolver);

        $options = $resolver->resolve();
        self::assertSame(0, $options['min_length']);
    }

    public function testEnhancedTextFilterTypeMinLengthAcceptsPositiveInt(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedTextFilterType())->configureOptions($resolver);

        $options = $resolver->resolve(['min_length' => 5]);
        self::assertSame(5, $options['min_length']);
    }

    public function testEnhancedTextFilterTypeRejectsNegativeMinLength(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedTextFilterType())->configureOptions($resolver);

        $this->expectException(Throwable::class);
        $resolver->resolve(['min_length' => -1]);
    }

    public function testEnhancedTextFilterTypeBuildViewExposesMinLength(): void
    {
        $view = new FormView();
        (new EnhancedTextFilterType())->buildView($view, $this->stubForm(), ['min_length' => 7]);
        self::assertSame(7, $view->vars['min_length']);
    }

    public function testEnhancedNumericFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_numeric_filter', (new EnhancedNumericFilterType())->getBlockPrefix());
    }

    public function testEnhancedNumericFilterTypeStepAndQuickRangesDefaults(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedNumericFilterType())->configureOptions($resolver);

        $options = $resolver->resolve();
        self::assertSame(0, $options['step']);
        self::assertSame([], $options['quick_ranges']);
    }

    public function testEnhancedNumericFilterTypeAcceptsCustomQuickRanges(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedNumericFilterType())->configureOptions($resolver);

        $ranges = [
            ['label' => '<100', 'min' => null, 'max' => 100],
            ['label' => '100-1000', 'min' => 100, 'max' => 1000],
        ];
        $options = $resolver->resolve(['quick_ranges' => $ranges]);
        self::assertSame($ranges, $options['quick_ranges']);
    }

    public function testEnhancedNumericFilterTypeRejectsQuickRangesWithoutLabel(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedNumericFilterType())->configureOptions($resolver);

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve(['quick_ranges' => [['min' => 0, 'max' => 10]]]);
    }

    public function testEnhancedNumericFilterTypeRejectsNegativeStep(): void
    {
        $resolver = new OptionsResolver();
        (new EnhancedNumericFilterType())->configureOptions($resolver);

        $this->expectException(Throwable::class);
        $resolver->resolve(['step' => -1]);
    }

    public function testEnhancedNumericFilterTypeBuildViewExposesStepAndQuickRanges(): void
    {
        $view = new FormView();
        (new EnhancedNumericFilterType())->buildView(
            $view,
            $this->stubForm(),
            ['step' => 0.01, 'quick_ranges' => [['label' => '<100']]],
        );
        self::assertSame(0.01, $view->vars['step']);
        self::assertSame([['label' => '<100']], $view->vars['quick_ranges']);
    }

    public function testEnhancedDateTimeFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_datetime_filter', (new EnhancedDateTimeFilterType())->getBlockPrefix());
    }

    public function testEnhancedBooleanFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_boolean_filter', (new EnhancedBooleanFilterType())->getBlockPrefix());
    }

    public function testEnhancedChoiceFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_choice_filter', (new EnhancedChoiceFilterType())->getBlockPrefix());
    }

    public function testEnhancedComparisonFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_comparison_filter', (new EnhancedComparisonFilterType())->getBlockPrefix());
    }

    public function testEnhancedEntityFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_entity_filter', (new EnhancedEntityFilterType())->getBlockPrefix());
    }

    public function testEnhancedArrayFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_enhanced_array_filter', (new EnhancedArrayFilterType())->getBlockPrefix());
    }

    public function testNotNullFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_not_null_filter', (new NotNullFilterType())->getBlockPrefix());
    }

    public function testNotNullFilterTypeValueConstantsAreDistinct(): void
    {
        // Behavioural property: the 3 values must be pairwise distinct
        // so the form's match arm in NotNullFilter::apply() can branch
        // unambiguously. Their literal strings are part of the public
        // contract (saved-view JSON, URL replay, host overrides).
        $values = [
            NotNullFilterType::VALUE_ANY,
            NotNullFilterType::VALUE_NOT_NULL,
            NotNullFilterType::VALUE_NULL,
        ];
        self::assertCount(3, array_unique($values), 'tri-state values must be distinct');
    }

    public function testNotNullFilterTypeLabelsDefault(): void
    {
        $resolver = new OptionsResolver();
        (new NotNullFilterType())->configureOptions($resolver);

        $options = $resolver->resolve();
        self::assertSame(['any' => 'Any', 'not_null' => 'Has value', 'null' => 'Empty'], $options['labels']);
        self::assertTrue($options['expanded']);
    }

    public function testNotNullFilterTypeLabelsCustomisable(): void
    {
        $resolver = new OptionsResolver();
        (new NotNullFilterType())->configureOptions($resolver);

        $options = $resolver->resolve(['labels' => ['any' => 'All', 'not_null' => 'Yes', 'null' => 'No']]);
        self::assertSame('All', $options['labels']['any']);
    }

    public function testBetweenDateFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_between_date_filter', (new BetweenDateFilterType())->getBlockPrefix());
    }

    public function testInFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_in_filter', (new InFilterType())->getBlockPrefix());
    }

    public function testFullTextSearchFilterTypeBlockPrefix(): void
    {
        self::assertSame('polysource_full_text_search_filter', (new FullTextSearchFilterType())->getBlockPrefix());
    }

    private function stubForm(): FormInterface
    {
        return $this->createMock(FormInterface::class);
    }
}
