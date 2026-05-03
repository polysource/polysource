<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ComparisonFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ComparisonFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\ComparisonFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedComparisonFilterType;

final class ComparisonFilterEnhancerTest extends TestCase
{
    private ComparisonFilterEnhancer $enhancer;
    private FilterDto $comparisonFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new ComparisonFilterEnhancer();

        $this->comparisonFilterDto = new FilterDto();
        $this->comparisonFilterDto->setFqcn(ComparisonFilter::class);
        $this->comparisonFilterDto->setProperty('priority');
        $this->comparisonFilterDto->setFormType(ComparisonFilterType::class);
        $this->comparisonFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        $this->unrelatedFilterDto = new FilterDto();
        $this->unrelatedFilterDto->setFqcn('EasyCorp\\Bundle\\EasyAdminBundle\\Filter\\TextFilter');
    }

    private function makeEntityDto(): EntityDto
    {
        return (new \ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();
    }

    private function makeAdminContext(): AdminContext
    {
        return (new \ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
    }

    public function test_supports_returns_true_for_comparison_filter(): void
    {
        self::assertTrue($this->enhancer->supports($this->comparisonFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function test_supports_returns_false_for_unrelated_filter(): void
    {
        self::assertFalse($this->enhancer->supports($this->unrelatedFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function test_configure_swaps_form_type_to_enhanced_one(): void
    {
        $this->enhancer->configure($this->comparisonFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame(EnhancedComparisonFilterType::class, $this->comparisonFilterDto->getFormType());
    }

    public function test_configure_preserves_upstream_options(): void
    {
        $this->enhancer->configure($this->comparisonFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame('EasyAdminBundle', $this->comparisonFilterDto->getFormTypeOptions()['translation_domain']);
    }
}
