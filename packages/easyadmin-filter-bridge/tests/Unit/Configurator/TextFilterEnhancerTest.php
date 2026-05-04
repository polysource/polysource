<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\TextFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedTextFilterType;
use ReflectionClass;

final class TextFilterEnhancerTest extends TestCase
{
    private TextFilterEnhancer $enhancer;
    private FilterDto $textFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new TextFilterEnhancer();

        $this->textFilterDto = new FilterDto();
        $this->textFilterDto->setFqcn(TextFilter::class);
        $this->textFilterDto->setProperty('description');
        $this->textFilterDto->setFormType(TextFilterType::class);
        $this->textFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        $this->unrelatedFilterDto = new FilterDto();
        $this->unrelatedFilterDto->setFqcn('EasyCorp\\Bundle\\EasyAdminBundle\\Filter\\BooleanFilter');
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

    public function testSupportsReturnsTrueForTextFilter(): void
    {
        self::assertTrue($this->enhancer->supports($this->textFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function testSupportsReturnsFalseForUnrelatedFilter(): void
    {
        self::assertFalse(
            $this->enhancer->supports($this->unrelatedFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()),
            'TextEnhancer must not interfere with non-Text filters',
        );
    }

    public function testConfigureSwapsFormTypeToEnhancedOne(): void
    {
        $this->enhancer->configure($this->textFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame(EnhancedTextFilterType::class, $this->textFilterDto->getFormType());
    }

    public function testConfigurePreservesUpstreamOptions(): void
    {
        $this->enhancer->configure($this->textFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame('EasyAdminBundle', $this->textFilterDto->getFormTypeOptions()['translation_domain']);
    }
}
