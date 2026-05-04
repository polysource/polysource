<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Bridge;

use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;
use ReflectionClass;

/**
 * Behavioural tests on the {@see Polysource} static facade —
 * verifies the four entry points produce wrappers that write the
 * expected customOption when a fluent setter is chained.
 *
 * Type-equality assertions are skipped (the static return types
 * already guarantee them at compile-time per PHPStan).
 */
final class PolysourceTest extends TestCase
{
    public function testFilterFluentChainPersistsOnTheUnderlyingDto(): void
    {
        $filter = TextFilter::new('name');
        Polysource::filter($filter)->tab('T')->group('G');

        $dto = $filter->getAsDto();
        self::assertSame('T', $dto->getCustomOption(BridgeOptions::TAB));
        self::assertSame('G', $dto->getCustomOption(BridgeOptions::GROUP));
    }

    public function testFieldFluentChainPersistsOnTheUnderlyingDto(): void
    {
        $field = BooleanField::new('flag');
        $cb = static fn (mixed $v): string => 'X';
        Polysource::field($field)->chipFormatter($cb);

        self::assertSame($cb, $field->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER));
    }

    public function testTabMarkerCarriesTheTabLabel(): void
    {
        $dto = Polysource::tab('Visibility')->getAsDto();

        self::assertTrue($dto->getCustomOption(BridgeOptions::IS_MARKER));
        self::assertSame('Visibility', $dto->getCustomOption(BridgeOptions::TAB));
    }

    public function testGroupMarkerCarriesTheGroupLabel(): void
    {
        $dto = Polysource::group('Active state')->getAsDto();

        self::assertTrue($dto->getCustomOption(BridgeOptions::IS_MARKER));
        self::assertSame('Active state', $dto->getCustomOption(BridgeOptions::GROUP));
    }

    public function testFacadeIsNotInstantiable(): void
    {
        $reflection = new ReflectionClass(Polysource::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
    }
}
