<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Field\BooleanField;
use Polysource\Core\Field\CodeField;
use Polysource\Core\Field\DateTimeField;
use Polysource\Core\Field\IdField;
use Polysource\Core\Field\TextField;

/**
 * Unit coverage for the 5 concrete field types shipped in v0.7.1.
 *
 * Each one is a one-liner over FieldTrait + a specific theme template;
 * the test surface is small but the contract is what hosts depend on.
 */
#[CoversClass(TextField::class)]
#[CoversClass(IdField::class)]
#[CoversClass(BooleanField::class)]
#[CoversClass(DateTimeField::class)]
#[CoversClass(CodeField::class)]
final class ConcreteFieldTypesTest extends TestCase
{
    #[Test]
    public function textFieldUsesTextTemplate(): void
    {
        $dto = TextField::new('name', 'Name')->getAsDto();

        self::assertSame('name', $dto->property);
        self::assertSame('Name', $dto->label);
        self::assertSame('@Polysource/field/text.html.twig', $dto->template);
    }

    #[Test]
    public function idFieldUsesIdTemplate(): void
    {
        $dto = IdField::new('id')->getAsDto();

        self::assertSame('@Polysource/field/id.html.twig', $dto->template);
    }

    #[Test]
    public function booleanFieldUsesBooleanTemplate(): void
    {
        $dto = BooleanField::new('active')->getAsDto();

        self::assertSame('@Polysource/field/boolean.html.twig', $dto->template);
    }

    #[Test]
    public function dateTimeFieldUsesDatetimeTemplate(): void
    {
        $dto = DateTimeField::new('createdAt')->getAsDto();

        self::assertSame('@Polysource/field/datetime.html.twig', $dto->template);
    }

    #[Test]
    public function codeFieldUsesCodeTemplate(): void
    {
        $dto = CodeField::new('payload')->getAsDto();

        self::assertSame('@Polysource/field/code.html.twig', $dto->template);
    }

    #[Test]
    public function fluentSettersChainAcrossFieldTypes(): void
    {
        // The trait's fluent API must work uniformly across all
        // concrete types — covers a regression where a poorly-written
        // factory could lose the chain.
        $field = TextField::new('email', 'E-mail')
            ->setSortable(true)
            ->setPermission('VIEW_EMAIL')
            ->onlyOnDetail();

        $dto = $field->getAsDto();

        self::assertTrue($dto->sortable);
        self::assertSame('VIEW_EMAIL', $dto->permission);
        self::assertSame(['detail'], $dto->pages);
    }
}
