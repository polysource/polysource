<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\NumericFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedNumericFilterType;
use ReflectionClass;

final class NumericFilterEnhancerTest extends TestCase
{
    private NumericFilterEnhancer $enhancer;
    private FilterDto $numericFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new NumericFilterEnhancer();

        $this->numericFilterDto = new FilterDto();
        $this->numericFilterDto->setFqcn(NumericFilter::class);
        $this->numericFilterDto->setProperty('price');
        $this->numericFilterDto->setFormType(NumericFilterType::class);
        $this->numericFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        $this->unrelatedFilterDto = new FilterDto();
        $this->unrelatedFilterDto->setFqcn('EasyCorp\\Bundle\\EasyAdminBundle\\Filter\\TextFilter');
    }

    /**
     * @return EntityDto<object>
     */
    private function makeEntityDto(): EntityDto
    {
        return (new ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();
    }

    /**
     * @return AdminContext<object>
     */
    private function makeAdminContext(): AdminContext
    {
        return (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
    }

    public function testSupportsReturnsTrueForNumericFilter(): void
    {
        self::assertTrue($this->enhancer->supports($this->numericFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function testSupportsReturnsFalseForUnrelatedFilter(): void
    {
        self::assertFalse($this->enhancer->supports($this->unrelatedFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function testConfigureSwapsFormTypeToEnhancedOne(): void
    {
        $this->enhancer->configure($this->numericFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame(EnhancedNumericFilterType::class, $this->numericFilterDto->getFormType());
    }

    public function testConfigurePreservesUpstreamOptions(): void
    {
        $this->enhancer->configure($this->numericFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame('EasyAdminBundle', $this->numericFilterDto->getFormTypeOptions()['translation_domain']);
    }
}
