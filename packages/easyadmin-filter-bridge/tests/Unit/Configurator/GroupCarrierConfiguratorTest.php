<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Configurator\GroupCarrierConfigurator;
use ReflectionClass;

/**
 * Behavioural test for {@see GroupCarrierConfigurator}.
 *
 * Covers the 4 paths:
 * - no `polysource_group` option set → no-op
 * - non-empty string group → moved from formTypeOptions to customOption
 * - empty string group → stripped from formTypeOptions, NOT carried
 *   (host opted out)
 * - non-string group → stripped, NOT carried (defensive)
 */
final class GroupCarrierConfiguratorTest extends TestCase
{
    public function testSupportsAlwaysTrue(): void
    {
        $configurator = new GroupCarrierConfigurator();
        $filterDto = new FilterDto();

        self::assertTrue($configurator->supports(
            $filterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        ));
    }

    public function testConfigureNoOpWhenOptionNotSet(): void
    {
        $filterDto = new FilterDto();
        $filterDto->setFormTypeOptions(['translation_domain' => 'EasyAdminBundle']);

        (new GroupCarrierConfigurator())->configure(
            $filterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        );

        self::assertSame(['translation_domain' => 'EasyAdminBundle'], $filterDto->getFormTypeOptions());
        self::assertNull($filterDto->getCustomOption('polysource_group'));
    }

    public function testConfigureCarriesNonEmptyStringIntoCustomOption(): void
    {
        $filterDto = new FilterDto();
        $filterDto->setFormTypeOption('polysource_group', 'Status');

        (new GroupCarrierConfigurator())->configure(
            $filterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        );

        self::assertSame('Status', $filterDto->getCustomOption('polysource_group'));
    }

    public function testConfigureSkipsEmptyString(): void
    {
        $filterDto = new FilterDto();
        $filterDto->setFormTypeOption('polysource_group', '');

        (new GroupCarrierConfigurator())->configure(
            $filterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        );

        self::assertNull($filterDto->getCustomOption('polysource_group'));
    }

    public function testConfigureSkipsNonString(): void
    {
        $filterDto = new FilterDto();
        $filterDto->setFormTypeOption('polysource_group', 42);

        (new GroupCarrierConfigurator())->configure(
            $filterDto,
            null,
            $this->makeEntityDto(),
            $this->makeAdminContext(),
        );

        self::assertNull($filterDto->getCustomOption('polysource_group'));
    }

    /**
     * @return EntityDto<object>
     */
    private function makeEntityDto(): EntityDto
    {
        /** @var EntityDto<object> $dto */
        $dto = (new ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();

        return $dto;
    }

    /**
     * @return AdminContext<object>
     */
    private function makeAdminContext(): AdminContext
    {
        /** @var AdminContext<object> $ctx */
        $ctx = (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();

        return $ctx;
    }
}
