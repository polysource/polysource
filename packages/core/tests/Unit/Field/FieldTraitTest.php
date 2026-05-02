<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Field\FieldDto;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

#[CoversClass(FieldTrait::class)]
#[CoversClass(FieldDto::class)]
final class FieldTraitTest extends TestCase
{
    #[Test]
    public function fluentBuilderProducesADto(): void
    {
        $field = TestField::new('email', 'Email')
            ->setTemplate('field/email.html.twig')
            ->setSortable(true)
            ->setPermission('USER_VIEW')
            ->setCustomOption('icon', 'envelope');

        $dto = $field->getAsDto();

        self::assertSame('email', $dto->property);
        self::assertSame('Email', $dto->label);
        self::assertSame('field/email.html.twig', $dto->template);
        self::assertSame('USER_VIEW', $dto->permission);
        self::assertTrue($dto->sortable);
        self::assertSame(['icon' => 'envelope'], $dto->customOptions);
    }

    #[Test]
    public function pageHelpersRestrictDisplay(): void
    {
        $only = TestField::new('id')->onlyOnIndex()->getAsDto();
        self::assertSame(['index'], $only->pages);
        self::assertTrue($only->isOnPage('index'));
        self::assertFalse($only->isOnPage('detail'));

        $hide = TestField::new('createdAt')->hideOnIndex()->getAsDto();
        self::assertNotContains('index', $hide->pages);
        self::assertContains('detail', $hide->pages);
    }
}

final class TestField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return new self($property, $label);
    }
}
