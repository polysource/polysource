<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ArrayFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ArrayFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\ArrayFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedArrayFilterType;

final class ArrayFilterEnhancerTest extends TestCase
{
    private ArrayFilterEnhancer $enhancer;
    private FilterDto $arrayFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new ArrayFilterEnhancer();

        $this->arrayFilterDto = new FilterDto();
        $this->arrayFilterDto->setFqcn(ArrayFilter::class);
        $this->arrayFilterDto->setProperty('tags');
        $this->arrayFilterDto->setFormType(ArrayFilterType::class);
        $this->arrayFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

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

    public function test_supports_returns_true_for_array_filter(): void
    {
        self::assertTrue($this->enhancer->supports($this->arrayFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function test_supports_returns_false_for_unrelated_filter(): void
    {
        self::assertFalse($this->enhancer->supports($this->unrelatedFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function test_configure_swaps_form_type_to_enhanced_one(): void
    {
        $this->enhancer->configure($this->arrayFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame(EnhancedArrayFilterType::class, $this->arrayFilterDto->getFormType());
    }

    public function test_configure_preserves_upstream_options(): void
    {
        $this->enhancer->configure($this->arrayFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame('EasyAdminBundle', $this->arrayFilterDto->getFormTypeOptions()['translation_domain']);
    }
}
