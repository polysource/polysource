<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\ChoiceFilterEnhancer;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedChoiceFilterType;

final class ChoiceFilterEnhancerTest extends TestCase
{
    private ChoiceFilterEnhancer $enhancer;
    private FilterDto $choiceFilterDto;
    private FilterDto $unrelatedFilterDto;

    protected function setUp(): void
    {
        $this->enhancer = new ChoiceFilterEnhancer();

        $this->choiceFilterDto = new FilterDto();
        $this->choiceFilterDto->setFqcn(ChoiceFilter::class);
        $this->choiceFilterDto->setProperty('status');
        $this->choiceFilterDto->setFormType(ChoiceFilterType::class);
        $this->choiceFilterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        $this->unrelatedFilterDto = new FilterDto();
        $this->unrelatedFilterDto->setFqcn('EasyCorp\\Bundle\\EasyAdminBundle\\Filter\\NumericFilter');
    }

    private function makeEntityDto(): EntityDto
    {
        return (new \ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();
    }

    private function makeAdminContext(): AdminContext
    {
        return (new \ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();
    }

    public function test_supports_returns_true_for_choice_filter(): void
    {
        self::assertTrue($this->enhancer->supports($this->choiceFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function test_supports_returns_false_for_unrelated_filter(): void
    {
        self::assertFalse($this->enhancer->supports($this->unrelatedFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext()));
    }

    public function test_configure_swaps_form_type_to_enhanced_one(): void
    {
        $this->enhancer->configure($this->choiceFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame(EnhancedChoiceFilterType::class, $this->choiceFilterDto->getFormType());
    }

    public function test_configure_adds_inline_default_false(): void
    {
        $this->enhancer->configure($this->choiceFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        $options = $this->choiceFilterDto->getFormTypeOptions();
        self::assertArrayHasKey('inline', $options);
        self::assertFalse(
            $options['inline'],
            'Default must be false to preserve upstream rendering — host apps opt in per-resource',
        );
    }

    public function test_configure_preserves_upstream_options(): void
    {
        $this->enhancer->configure($this->choiceFilterDto, null, $this->makeEntityDto(), $this->makeAdminContext());

        self::assertSame('EasyAdminBundle', $this->choiceFilterDto->getFormTypeOptions()['translation_domain']);
    }
}
