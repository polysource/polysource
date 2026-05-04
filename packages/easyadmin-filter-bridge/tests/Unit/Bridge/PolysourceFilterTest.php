<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Bridge;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Bridge\PolysourceFilter;
use ReflectionClass;
use stdClass;

/**
 * Behavioural tests for {@see PolysourceFilter}.
 *
 * Verifies:
 * - fluent setters return $this
 * - each setter writes the right BridgeOptions key on the wrapped FilterDto
 * - apply() forwards to the wrapped filter
 * - getAsDto() returns the wrapped filter's DTO
 * - renderer() also updates FilterDto::formType (declarative + runtime sync)
 */
final class PolysourceFilterTest extends TestCase
{
    public function testTabWritesCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $result = $proxy->tab('Visibility');

        self::assertSame($proxy, $result);
        self::assertSame('Visibility', $filter->getAsDto()->getCustomOption(BridgeOptions::TAB));
    }

    public function testGroupWritesCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $proxy = PolysourceFilter::on($filter);

        $result = $proxy->group('Active state');

        self::assertSame($proxy, $result);
        self::assertSame('Active state', $filter->getAsDto()->getCustomOption(BridgeOptions::GROUP));
    }

    public function testChipFormatterWritesCustomOption(): void
    {
        $filter = TextFilter::new('name');
        $callable = static fn (mixed $v): string => 'X';

        PolysourceFilter::on($filter)->chipFormatter($callable);

        self::assertSame($callable, $filter->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER));
    }

    public function testRendererSyncsFormTypeAndCustomOption(): void
    {
        $filter = TextFilter::new('name');

        PolysourceFilter::on($filter)->renderer(stdClass::class);

        self::assertSame(stdClass::class, $filter->getAsDto()->getCustomOption(BridgeOptions::RENDERER));
        self::assertSame(stdClass::class, $filter->getAsDto()->getFormType());
    }

    public function testFluentChainAccumulatesAllOptions(): void
    {
        $filter = TextFilter::new('name');
        $cb = static fn (mixed $v): string => 'X';

        PolysourceFilter::on($filter)
            ->tab('T')
            ->group('G')
            ->chipFormatter($cb)
            ->meta('host.custom', 42);

        $dto = $filter->getAsDto();
        self::assertSame('T', $dto->getCustomOption(BridgeOptions::TAB));
        self::assertSame('G', $dto->getCustomOption(BridgeOptions::GROUP));
        self::assertSame($cb, $dto->getCustomOption(BridgeOptions::CHIP_FORMATTER));
        self::assertSame(42, $dto->getCustomOption('host.custom'));
    }

    public function testApplyForwardsToWrappedFilter(): void
    {
        $sentinel = new stdClass();
        $sentinel->invoked = false;
        $inner = new class($sentinel) implements \EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface {
            public function __construct(private stdClass $sentinel)
            {
            }

            public function apply(QueryBuilder $qb, FilterDataDto $d, ?\EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto $f, EntityDto $e): void
            {
                $this->sentinel->invoked = true;
            }

            public function getAsDto(): FilterDto
            {
                return new FilterDto();
            }

            public function __toString(): string
            {
                return 'inner';
            }
        };

        PolysourceFilter::on($inner)->apply(
            $this->createMock(QueryBuilder::class),
            $this->makeFilterData(),
            null,
            (new ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor(),
        );

        self::assertTrue($sentinel->invoked);
    }

    public function testToStringReturnsWrappedFilterToString(): void
    {
        self::assertSame('name', (string) PolysourceFilter::on(TextFilter::new('name')));
    }

    private function makeFilterData(): FilterDataDto
    {
        $dto = new FilterDto();
        $dto->setProperty('p');

        return FilterDataDto::new(0, $dto, 'e', ['comparison' => '=', 'value' => null]);
    }
}
