<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\EntityFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\EntityFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedEntityFilterType;
use ReflectionClass;

final class EntityFilterEnhancerTest extends TestCase
{
    private EntityFilterEnhancer $enhancer;
    private FilterDto $entityFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new EntityFilterEnhancer();

        $this->entityFilterDto = new FilterDto();
        $this->entityFilterDto->setFqcn(EntityFilter::class);
        $this->entityFilterDto->setProperty('category');
        $this->entityFilterDto->setFormType(EntityFilterType::class);
        $this->entityFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

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

    public function testSupportsReturnsTrueForEntityFilter(): void
    {
        self::assertTrue($this->enhancer->supports($this->entityFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function testSupportsReturnsFalseForUnrelatedFilter(): void
    {
        self::assertFalse($this->enhancer->supports($this->unrelatedFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function testConfigureSwapsFormTypeToEnhancedOne(): void
    {
        $this->enhancer->configure($this->entityFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame(EnhancedEntityFilterType::class, $this->entityFilterDto->getFormType());
    }

    public function testConfigurePreservesUpstreamOptions(): void
    {
        $this->enhancer->configure($this->entityFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame('EasyAdminBundle', $this->entityFilterDto->getFormTypeOptions()['translation_domain']);
    }
}
