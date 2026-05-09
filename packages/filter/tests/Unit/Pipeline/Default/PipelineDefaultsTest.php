<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Pipeline\Default;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\Default\ArrayListFormatter;
use Polysource\Filter\Pipeline\Default\ArrayListMapper;
use Polysource\Filter\Pipeline\Default\ArrayListRenderer;
use Polysource\Filter\Pipeline\Default\BooleanFormatter;
use Polysource\Filter\Pipeline\Default\BooleanMapper;
use Polysource\Filter\Pipeline\Default\BooleanRenderer;
use Polysource\Filter\Pipeline\Default\ChoiceFormatter;
use Polysource\Filter\Pipeline\Default\ChoiceMapper;
use Polysource\Filter\Pipeline\Default\ChoiceRenderer;
use Polysource\Filter\Pipeline\Default\DatetimeFormatter;
use Polysource\Filter\Pipeline\Default\DatetimeMapper;
use Polysource\Filter\Pipeline\Default\DatetimeRenderer;
use Polysource\Filter\Pipeline\Default\EntityFormatter;
use Polysource\Filter\Pipeline\Default\EntityMapper;
use Polysource\Filter\Pipeline\Default\EntityRenderer;
use Polysource\Filter\Pipeline\Default\NumericFormatter;
use Polysource\Filter\Pipeline\Default\NumericMapper;
use Polysource\Filter\Pipeline\Default\NumericRenderer;
use Polysource\Filter\Pipeline\Default\TextFormatter;
use Polysource\Filter\Pipeline\Default\TextMapper;
use Polysource\Filter\Pipeline\Default\TextRenderer;

/**
 * Pipeline contract pinning for the 7 default filter types
 * (text, numeric, datetime, boolean, choice, array_list, entity).
 *
 * Each type ships 3 pipeline pieces (Mapper / Formatter / Renderer);
 * this test exercises the public surface of all 21 classes in one
 * place. Behavioural depth-tests live in higher-level integration
 * tests (FilterServiceTest, FilterCollectionTypeTest).
 */
final class PipelineDefaultsTest extends TestCase
{
    // ─── Mappers ─────────────────────────────────────────────────────

    public function testTextMapperSupportsItsNameAndRoundtrips(): void
    {
        $mapper = new TextMapper();
        self::assertTrue($mapper->supports('text'));
        self::assertFalse($mapper->supports('numeric'));

        $criterion = $mapper->fromRequest('reference', ['comparison' => 'like', 'value' => 'ORD']);
        self::assertSame('reference', $criterion->property);
        self::assertSame('like', $criterion->operator);
        self::assertSame(['ORD'], $criterion->values);

        $data = $mapper->toFormData($criterion);
        self::assertSame(['comparison' => 'like', 'value' => 'ORD'], $data);
    }

    public function testTextMapperDefaultsToLikeOperatorWhenComparisonAbsent(): void
    {
        $criterion = (new TextMapper())->fromRequest('name', ['value' => 'foo']);
        self::assertSame('like', $criterion->operator);
    }

    public function testTextMapperDropsEmptyValueFromCriterion(): void
    {
        $criterion = (new TextMapper())->fromRequest('name', ['comparison' => 'like', 'value' => '']);
        self::assertSame([], $criterion->values);
    }

    public function testNumericMapperSupportsItsNameAndRoundtrips(): void
    {
        $mapper = new NumericMapper();
        self::assertTrue($mapper->supports('numeric'));
        self::assertFalse($mapper->supports('text'));

        $criterion = $mapper->fromRequest('priceCents', ['comparison' => '>=', 'value' => '5000']);
        self::assertSame('priceCents', $criterion->property);
        self::assertSame('>=', $criterion->operator);
        self::assertSame(['5000'], $criterion->values);

        $data = $mapper->toFormData($criterion);
        self::assertSame('>=', $data['comparison']);
        self::assertSame('5000', $data['value']);
    }

    public function testNumericMapperPreservesValue2WhenBetween(): void
    {
        $criterion = (new NumericMapper())->fromRequest('priceCents', [
            'comparison' => 'between',
            'value' => '100',
            'value2' => '500',
        ]);
        self::assertSame(['100', '500'], $criterion->values);

        $data = (new NumericMapper())->toFormData($criterion);
        self::assertSame('100', $data['value']);
        self::assertSame('500', $data['value2']);
    }

    public function testDatetimeMapperRoundtripsValueAndValue2(): void
    {
        $mapper = new DatetimeMapper();
        self::assertTrue($mapper->supports('datetime'));

        $criterion = $mapper->fromRequest('createdAt', [
            'comparison' => 'between',
            'value' => '2026-01-01',
            'value2' => '2026-12-31',
        ]);
        self::assertSame(['2026-01-01', '2026-12-31'], $criterion->values);
    }

    public function testDatetimeMapperDefaultsToEqualsWhenComparisonAbsent(): void
    {
        $criterion = (new DatetimeMapper())->fromRequest('createdAt', ['value' => '2026-05-01']);
        self::assertSame('=', $criterion->operator);
    }

    public function testBooleanMapperSupportsItsNameAndCoercesScalarValue(): void
    {
        $mapper = new BooleanMapper();
        self::assertTrue($mapper->supports('boolean'));

        $criterion = $mapper->fromRequest('isActive', ['value' => '1']);
        self::assertSame(['1'], $criterion->values);

        $data = $mapper->toFormData($criterion);
        self::assertNotEmpty($data);
    }

    public function testChoiceMapperWrapsTheRawValueAtIndexZero(): void
    {
        // Choice mapper treats `value` as a single opaque element —
        // it ends up at $values[0] regardless of whether the raw
        // input is a scalar or an array. Hosts that need per-element
        // handling rely on Symfony Form's ChoiceType to pre-flatten
        // before calling the mapper.
        $mapper = new ChoiceMapper();
        self::assertTrue($mapper->supports('choice'));

        $criterion = $mapper->fromRequest('status', [
            'comparison' => '=',
            'value' => ['paid', 'shipped'],
        ]);
        self::assertSame([['paid', 'shipped']], $criterion->values);

        $data = $mapper->toFormData($criterion);
        self::assertSame(['paid', 'shipped'], $data['value']);
    }

    public function testChoiceMapperPreservesScalarValueForSingleSelect(): void
    {
        $criterion = (new ChoiceMapper())->fromRequest('status', [
            'comparison' => '=',
            'value' => 'paid',
        ]);
        self::assertSame(['paid'], $criterion->values);
    }

    public function testArrayListMapperDefaultsToInOperator(): void
    {
        $mapper = new ArrayListMapper();
        self::assertTrue($mapper->supports('array'));

        $criterion = $mapper->fromRequest('tags', ['value' => ['a', 'b', 'c']]);
        self::assertSame('in', $criterion->operator);
        self::assertSame([['a', 'b', 'c']], $criterion->values);
    }

    public function testEntityMapperWrapsArrayValueAtIndexZero(): void
    {
        $mapper = new EntityMapper();
        self::assertTrue($mapper->supports('entity'));

        $criterion = $mapper->fromRequest('customer', [
            'comparison' => '=',
            'value' => ['019df-uuid-1', '019df-uuid-2'],
        ]);
        self::assertSame([['019df-uuid-1', '019df-uuid-2']], $criterion->values);
    }

    // ─── Formatters ──────────────────────────────────────────────────

    public function testTextFormatterFormatsAsPropertyOperatorValue(): void
    {
        $formatter = new TextFormatter();
        self::assertTrue($formatter->supports('text'));

        $output = $formatter->format(new FilterCriterion('reference', 'like', ['ORD']));
        self::assertStringContainsString('reference', $output);
        self::assertStringContainsString('like', $output);
        self::assertStringContainsString('ORD', $output);
    }

    public function testNumericFormatterIncludesProperty(): void
    {
        $formatter = new NumericFormatter();
        self::assertTrue($formatter->supports('numeric'));

        $output = $formatter->format(new FilterCriterion('priceCents', '>=', ['5000']));
        self::assertStringContainsString('priceCents', $output);
        self::assertStringContainsString('5000', $output);
    }

    public function testDatetimeFormatterRendersBothValuesForBetween(): void
    {
        $formatter = new DatetimeFormatter();
        self::assertTrue($formatter->supports('datetime'));

        $output = $formatter->format(new FilterCriterion('createdAt', 'between', ['2026-01-01', '2026-12-31']));
        self::assertStringContainsString('createdAt', $output);
        self::assertStringContainsString('2026-01-01', $output);
        self::assertStringContainsString('2026-12-31', $output);
    }

    public function testBooleanFormatterReturnsPropertyAndOperatorOnly(): void
    {
        $formatter = new BooleanFormatter();
        self::assertTrue($formatter->supports('boolean'));

        $output = $formatter->format(new FilterCriterion('isActive', '=', ['1']));
        self::assertStringContainsString('isActive', $output);
    }

    public function testBooleanFormatterHandlesEmptyValues(): void
    {
        $output = (new BooleanFormatter())->format(new FilterCriterion('isActive', '=', []));
        self::assertStringContainsString('isActive', $output);
    }

    public function testChoiceFormatterIncludesAllValues(): void
    {
        $formatter = new ChoiceFormatter();
        self::assertTrue($formatter->supports('choice'));

        $output = $formatter->format(new FilterCriterion('status', '=', ['paid', 'shipped']));
        self::assertStringContainsString('paid', $output);
        self::assertStringContainsString('shipped', $output);
    }

    public function testArrayListFormatterFormatsCorrectly(): void
    {
        $formatter = new ArrayListFormatter();
        self::assertTrue($formatter->supports('array'));

        $output = $formatter->format(new FilterCriterion('tags', '=', ['php', 'symfony']));
        self::assertStringContainsString('php', $output);
        self::assertStringContainsString('symfony', $output);
    }

    public function testEntityFormatterFormatsCorrectly(): void
    {
        $formatter = new EntityFormatter();
        self::assertTrue($formatter->supports('entity'));

        $output = $formatter->format(new FilterCriterion('customer', '=', ['019df-uuid-1']));
        self::assertStringContainsString('customer', $output);
        self::assertStringContainsString('019df-uuid-1', $output);
    }

    // ─── Renderers ───────────────────────────────────────────────────

    public function testTextRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new TextRenderer();
        self::assertTrue($renderer->supports('text'));
        self::assertFalse($renderer->supports('numeric'));
        self::assertNotEmpty($renderer->getFormType());
        self::assertStringContainsString('TextType', $renderer->getFormType());
    }

    public function testNumericRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new NumericRenderer();
        self::assertTrue($renderer->supports('numeric'));
        self::assertNotEmpty($renderer->getFormType());
    }

    public function testDatetimeRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new DatetimeRenderer();
        self::assertTrue($renderer->supports('datetime'));
        self::assertNotEmpty($renderer->getFormType());
    }

    public function testBooleanRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new BooleanRenderer();
        self::assertTrue($renderer->supports('boolean'));
        self::assertNotEmpty($renderer->getFormType());
    }

    public function testChoiceRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new ChoiceRenderer();
        self::assertTrue($renderer->supports('choice'));
        self::assertNotEmpty($renderer->getFormType());
    }

    public function testArrayListRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new ArrayListRenderer();
        self::assertTrue($renderer->supports('array'));
        self::assertNotEmpty($renderer->getFormType());
    }

    public function testEntityRendererSupportsItsNameAndExposesFormType(): void
    {
        $renderer = new EntityRenderer();
        self::assertTrue($renderer->supports('entity'));
        self::assertNotEmpty($renderer->getFormType());
    }
}
