<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\BooleanFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\BooleanFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;

/**
 * Mirrors {@see DateTimeFilterEnhancerTest} for the BooleanFilter case.
 *
 * Combined with the DateTime test, this proves the bridge pattern scales
 * across filter types: each Configurator targets exactly one upstream
 * filter (via its FQCN) and swaps the formType to a richer one — without
 * any mutual interference and without any EasyAdmin code change.
 */
final class BooleanFilterEnhancerTest extends TestCase
{
    private BooleanFilterEnhancer $enhancer;
    private FilterDto $booleanFilterDto;
    private FilterDto $dateTimeFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new BooleanFilterEnhancer();

        $this->booleanFilterDto = new FilterDto();
        $this->booleanFilterDto->setFqcn(BooleanFilter::class);
        $this->booleanFilterDto->setProperty('isActive');
        $this->booleanFilterDto->setFormType(BooleanFilterType::class);
        $this->booleanFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        // A DateTime filter — supports() must return false, BooleanEnhancer
        // must NOT pick it up (DateTimeEnhancer will).
        $this->dateTimeFilterDto = new FilterDto();
        $this->dateTimeFilterDto->setFqcn('EasyCorp\\Bundle\\EasyAdminBundle\\Filter\\DateTimeFilter');
    }

    private function makeEntityDto(): EntityDto
    {
        return (new \ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();
    }

    private function makeAdminContext(): AdminContext
    {
        return (new \ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
    }

    public function test_supports_returns_true_for_boolean_filter(): void
    {
        self::assertTrue(
            $this->enhancer->supports(
                $this->booleanFilterDto,
                null,
                $this->makeEntityDto(),
                $this->makeAdminContext(),
            ),
        );
    }

    public function test_supports_returns_false_for_datetime_filter(): void
    {
        self::assertFalse(
            $this->enhancer->supports(
                $this->dateTimeFilterDto,
                null,
                $this->makeEntityDto(),
                $this->makeAdminContext(),
            ),
            'BooleanEnhancer must not interfere with DateTimeFilter — each Configurator owns exactly one filter type',
        );
    }

    public function test_configure_swaps_form_type_to_enhanced_one(): void
    {
        $this->enhancer->configure(
            $this->booleanFilterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        );

        self::assertSame(
            EnhancedBooleanFilterType::class,
            $this->booleanFilterDto->getFormType(),
        );
    }

    public function test_configure_preserves_upstream_options(): void
    {
        $this->enhancer->configure(
            $this->booleanFilterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        );

        $options = $this->booleanFilterDto->getFormTypeOptions();
        self::assertSame('EasyAdminBundle', $options['translation_domain']);
    }
}
