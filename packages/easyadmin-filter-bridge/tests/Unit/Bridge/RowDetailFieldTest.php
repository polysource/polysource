<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Bridge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;
use Polysource\EasyAdminFilterBridge\Bridge\RowDetailField;

final class RowDetailFieldTest extends TestCase
{
    #[Test]
    public function newConfiguresAVirtualIndexOnlyChevronCell(): void
    {
        $dto = RowDetailField::new()->getAsDto();

        self::assertSame(RowDetailField::PROPERTY, $dto->getProperty());
        self::assertTrue($dto->isVirtual());
        self::assertFalse($dto->getLabel());
        self::assertSame(
            '@PolysourceEasyAdminFilterBridge/crud/field/row_detail.html.twig',
            $dto->getTemplatePath(),
        );
        self::assertFalse($dto->getCustomOption(RowDetailField::OPTION_RELOAD_ON_OPEN));
        self::assertStringContainsString('polysource-row-detail-cell', $dto->getCssClass());
    }

    #[Test]
    public function reloadOnOpenFlipsTheCustomOption(): void
    {
        $dto = RowDetailField::new()->reloadOnOpen()->getAsDto();

        self::assertTrue($dto->getCustomOption(RowDetailField::OPTION_RELOAD_ON_OPEN));
    }

    #[Test]
    public function facadeShortcutReturnsAConfiguredField(): void
    {
        self::assertSame(
            RowDetailField::PROPERTY,
            Polysource::rowDetail()->getAsDto()->getProperty(),
        );
    }
}
