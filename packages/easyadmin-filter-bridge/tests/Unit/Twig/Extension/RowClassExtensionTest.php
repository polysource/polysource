<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Twig\Extension\RowClassExtension;
use stdClass;
use Twig\TwigFunction;

#[CoversClass(RowClassExtension::class)]
final class RowClassExtensionTest extends TestCase
{
    #[Test]
    public function exposesThePolysourceRowClassTwigFunction(): void
    {
        $extension = new RowClassExtension();

        $names = array_map(
            static fn (TwigFunction $f): string => $f->getName(),
            $extension->getFunctions(),
        );

        self::assertContains('polysource_row_class', $names);
        self::assertCount(1, $names);
    }

    #[Test]
    public function resolvesGetterAndMapsValueToClass(): void
    {
        $extension = new RowClassExtension();
        $entity = new RowClassFixtureEntity('refunded');

        self::assertSame(
            'table-danger',
            $extension->rowClass($entity, 'status', [
                'refunded' => 'table-danger',
                'archived' => 'text-muted',
            ]),
        );
    }

    #[Test]
    public function returnsDefaultWhenValueIsNotInMap(): void
    {
        $extension = new RowClassExtension();
        $entity = new RowClassFixtureEntity('shipped');

        self::assertSame(
            '',
            $extension->rowClass($entity, 'status', ['refunded' => 'table-danger']),
        );
        self::assertSame(
            'default-row',
            $extension->rowClass(
                $entity,
                'status',
                ['refunded' => 'table-danger'],
                'default-row',
            ),
        );
    }

    #[Test]
    public function resolvesBooleanIsserGetter(): void
    {
        $extension = new RowClassExtension();
        $entity = new RowClassFixtureEntity('shipped');
        $entity->active = true;

        $cls = $extension->rowClass($entity, 'active', [
            'true' => 'highlighted',
            'false' => 'dim',
        ]);

        self::assertSame('highlighted', $cls);
    }

    #[Test]
    public function fallsBackToPublicPropertyWhenNoGetter(): void
    {
        $extension = new RowClassExtension();
        $entity = new stdClass();
        $entity->status = 'archived';

        self::assertSame(
            'text-muted',
            $extension->rowClass($entity, 'status', [
                'refunded' => 'table-danger',
                'archived' => 'text-muted',
            ]),
        );
    }

    #[Test]
    public function returnsDefaultForUnknownProperty(): void
    {
        $extension = new RowClassExtension();
        $entity = new RowClassFixtureEntity('shipped');

        self::assertSame(
            '',
            $extension->rowClass($entity, 'nonexistent', ['x' => 'y']),
        );
    }

    #[Test]
    public function readsBackedEnumValue(): void
    {
        $extension = new RowClassExtension();
        $entity = new RowClassFixtureEnumEntity();

        // BackedEnum's value is the underlying string, so the map
        // should be keyed by that.
        $cls = $extension->rowClass($entity, 'status', [
            'refunded' => 'table-danger',
        ]);

        self::assertSame('table-danger', $cls);
    }
}

final class RowClassFixtureEntity
{
    public bool $active = false;

    public function __construct(private readonly string $status)
    {
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}

enum RowClassFixtureStatus: string
{
    case Shipped = 'shipped';
    case Refunded = 'refunded';
}

final class RowClassFixtureEnumEntity
{
    public function getStatus(): RowClassFixtureStatus
    {
        return RowClassFixtureStatus::Refunded;
    }
}
