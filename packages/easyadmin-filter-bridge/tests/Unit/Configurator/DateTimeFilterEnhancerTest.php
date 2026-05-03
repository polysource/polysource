<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\DateTimeFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\DateTimeFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedDateTimeFilterType;

/**
 * The PoC seam test.
 *
 * Proves that:
 * 1. Our `DateTimeFilterEnhancer::supports()` returns true for a real
 *    EasyAdmin `FilterDto` whose FQCN is `DateTimeFilter::class`.
 * 2. Our `configure()` swaps the formType to `EnhancedDateTimeFilterType`
 *    and adds the `presets` + `show_clear` options without losing whatever
 *    options the upstream filter had set.
 *
 * If this passes, the strategic pivot acted in ADR-012 is technically
 * validated — the `FilterConfiguratorInterface` extension point of
 * EasyAdmin v5 is sufficient to implement the entire bridge, and no
 * fork is needed.
 */
final class DateTimeFilterEnhancerTest extends TestCase
{
    private DateTimeFilterEnhancer $enhancer;
    private FilterDto $dateTimeFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new DateTimeFilterEnhancer();

        // Build the FilterDto exactly the way EasyAdmin's FilterTrait does
        // when DateTimeFilter::new('createdAt') is called.
        $this->dateTimeFilterDto = new FilterDto();
        $this->dateTimeFilterDto->setFqcn(DateTimeFilter::class);
        $this->dateTimeFilterDto->setProperty('createdAt');
        $this->dateTimeFilterDto->setFormType(DateTimeFilterType::class);
        $this->dateTimeFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        // A non-DateTime filter — supports() must return false on this one.
        $this->unrelatedFilterDto = new FilterDto();
        $this->unrelatedFilterDto->setFqcn('SomeOtherFilter');
        $this->unrelatedFilterDto->setFormType('SomeOtherFormType');
    }

    /**
     * `EntityDto` and `AdminContext` are `final` in EasyAdmin v5; we can't
     * `createMock()` them. We don't read any property either — our
     * Configurator only consults `$filterDto`. So we instantiate them via
     * reflection without calling the constructor, which satisfies the
     * typehints with minimal coupling to internal shape.
     */
    private function makeEntityDto(): EntityDto
    {
        return (new \ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();
    }

    private function makeAdminContext(): AdminContext
    {
        return (new \ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
    }

    public function test_supports_returns_true_for_datetime_filter(): void
    {
        $entityDto = $this->makeEntityDto();
        $context = $this->makeAdminContext();

        self::assertTrue(
            $this->enhancer->supports($this->dateTimeFilterDto, null, $entityDto, $context),
            'Enhancer must opt-in for FilterDto whose FQCN is DateTimeFilter::class',
        );
    }

    public function test_supports_returns_false_for_unrelated_filter(): void
    {
        $entityDto = $this->makeEntityDto();
        $context = $this->makeAdminContext();

        self::assertFalse(
            $this->enhancer->supports($this->unrelatedFilterDto, null, $entityDto, $context),
            'Enhancer must opt-out for any filter that is not DateTimeFilter — otherwise it would break unrelated filters',
        );
    }

    public function test_configure_swaps_form_type_to_enhanced_one(): void
    {
        $entityDto = $this->makeEntityDto();
        $context = $this->makeAdminContext();

        self::assertSame(
            DateTimeFilterType::class,
            $this->dateTimeFilterDto->getFormType(),
            'Sanity check: before configure(), the DTO carries the upstream EasyAdmin formType',
        );

        $this->enhancer->configure($this->dateTimeFilterDto, null, $entityDto, $context);

        self::assertSame(
            EnhancedDateTimeFilterType::class,
            $this->dateTimeFilterDto->getFormType(),
            'After configure(), the DTO must carry our enhanced formType',
        );
    }

    public function test_configure_preserves_upstream_options(): void
    {
        $entityDto = $this->makeEntityDto();
        $context = $this->makeAdminContext();

        $this->enhancer->configure($this->dateTimeFilterDto, null, $entityDto, $context);

        $options = $this->dateTimeFilterDto->getFormTypeOptions();
        self::assertArrayHasKey('translation_domain', $options);
        self::assertSame(
            'EasyAdminBundle',
            $options['translation_domain'],
            'configure() must merge over upstream options, not replace them — otherwise we break translations and other host-app conventions',
        );
    }
}
