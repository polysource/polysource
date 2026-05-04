<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Bridge;

use BadMethodCallException;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Bridge\PolysourceField;

/**
 * Behavioural tests for {@see PolysourceField}.
 */
final class PolysourceFieldTest extends TestCase
{
    public function testChipFormatterWritesCustomOption(): void
    {
        $field = BooleanField::new('isVisible');
        $cb = static fn (mixed $v): string => true === $v ? 'Actif' : 'Inactif';

        $proxy = PolysourceField::on($field);
        $result = $proxy->chipFormatter($cb);

        self::assertSame($proxy, $result);
        self::assertSame($cb, $field->getAsDto()->getCustomOption(BridgeOptions::CHIP_FORMATTER));
    }

    public function testMetaWritesArbitraryCustomOption(): void
    {
        $field = BooleanField::new('isVisible');

        PolysourceField::on($field)->meta('export_format', 'yn');

        self::assertSame('yn', $field->getAsDto()->getCustomOption('export_format'));
    }

    public function testGetAsDtoReturnsWrappedFieldDto(): void
    {
        $field = BooleanField::new('isVisible');

        self::assertSame($field->getAsDto(), PolysourceField::on($field)->getAsDto());
    }

    public function testNewThrowsBecauseProxyMustWrapAnExistingField(): void
    {
        $this->expectException(BadMethodCallException::class);
        PolysourceField::new('whatever');
    }
}
