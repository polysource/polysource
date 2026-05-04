<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Bridge;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Bridge\PolysourceMarker;
use ReflectionClass;

/**
 * Behavioural tests for {@see PolysourceMarker}.
 */
final class PolysourceMarkerTest extends TestCase
{
    public function testTabMarkerCarriesIsMarkerAndTabCustomOptions(): void
    {
        $marker = PolysourceMarker::tab('Visibility');
        $dto = $marker->getAsDto();

        self::assertTrue($dto->getCustomOption(BridgeOptions::IS_MARKER));
        self::assertSame('Visibility', $dto->getCustomOption(BridgeOptions::TAB));
        self::assertNull($dto->getCustomOption(BridgeOptions::GROUP));
    }

    public function testGroupMarkerCarriesIsMarkerAndGroupCustomOptions(): void
    {
        $marker = PolysourceMarker::group('Active state');
        $dto = $marker->getAsDto();

        self::assertTrue($dto->getCustomOption(BridgeOptions::IS_MARKER));
        self::assertSame('Active state', $dto->getCustomOption(BridgeOptions::GROUP));
        self::assertNull($dto->getCustomOption(BridgeOptions::TAB));
    }

    public function testMarkersHaveDistinctSyntheticPropertyNames(): void
    {
        $a = PolysourceMarker::tab('X');
        $b = PolysourceMarker::tab('X');

        self::assertNotSame($a->getAsDto()->getProperty(), $b->getAsDto()->getProperty());
        // Property names start with the synthetic prefix so the
        // processor (and any sanity check) can spot them quickly.
        self::assertStringStartsWith('__polysource_marker_', $a->getAsDto()->getProperty());
    }

    public function testApplyIsNoOpAndNeverMutatesQueryBuilder(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');
        $qb->expects(self::never())->method('setParameter');

        $marker = PolysourceMarker::tab('Anything');
        $marker->apply($qb, $this->makeFilterData(), null, $this->makeEntityDto());
    }

    public function testFilterFqcnIsTheMarkerClass(): void
    {
        self::assertSame(PolysourceMarker::class, PolysourceMarker::tab('X')->getAsDto()->getFqcn());
    }

    private function makeFilterData(): FilterDataDto
    {
        $dto = new FilterDto();
        $dto->setProperty('p');

        return FilterDataDto::new(0, $dto, 'e', ['comparison' => '', 'value' => null]);
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
}
