<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Controller;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Controller\ControllerSupport;
use Polysource\Core\Query\DataRecord;
use Stringable;

/**
 * Unit coverage for {@see ControllerSupport::synthesiseFieldsFromRecord}
 * — the empty-fields fallback that prevents rows-without-columns when
 * a Resource declares no fields.
 */
#[CoversClass(ControllerSupport::class)]
final class ControllerSupportTest extends TestCase
{
    #[Test]
    public function synthesisesOneFieldPerPropertyKey(): void
    {
        $record = new DataRecord(
            identifier: 'r-1',
            properties: ['name' => 'Alice', 'age' => 42, 'active' => true],
        );

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertCount(3, $fields);
        self::assertSame(['name', 'age', 'active'], array_map(static fn ($f) => $f->property, $fields));
    }

    #[Test]
    public function usesPropertyNameAsLabelByDefault(): void
    {
        $record = new DataRecord(identifier: 'r-1', properties: ['createdAt' => '2026-05-15']);

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertSame('createdAt', $fields[0]->label);
    }

    #[Test]
    public function skipsEmptyOrNonStringPropertyKeys(): void
    {
        // DataRecord::properties is array<string, mixed> per the type
        // contract, but defensively the fallback must not blow up on
        // malformed maps that crept through (e.g. an adapter that
        // emitted '' as a key during early development).
        $record = new DataRecord(identifier: 'r-1', properties: ['' => 'empty', 'ok' => 'value']);

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertCount(1, $fields);
        self::assertSame('ok', $fields[0]->property);
    }

    #[Test]
    public function returnsEmptyListWhenRecordHasNoProperties(): void
    {
        $record = new DataRecord(identifier: 'r-1', properties: []);

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertSame([], $fields);
    }

    #[Test]
    public function rawSourceIsNotSurfaced(): void
    {
        // `$rawSource` is the @internal escape hatch (ADR-011 item A3);
        // synthesised fields must NEVER leak it to the UI because
        // adapter-specific shapes would surface to host templates.
        $record = new DataRecord(
            identifier: 'r-1',
            properties: ['name' => 'Alice'],
            rawSource: (object) ['internal' => 'secret'],
        );

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertSame(['name'], array_map(static fn ($f) => $f->property, $fields));
    }

    #[Test]
    public function skipsNonStringableObjects(): void
    {
        // DateTimeImmutable and other non-Stringable objects would
        // blow up the generic Twig template with "Object of class X
        // could not be converted to string". The synthesis must drop
        // them so the page renders. Hosts with rich-typed properties
        // declare typed fields (DateTimeField, etc.) explicitly.
        $record = new DataRecord(
            identifier: 'r-1',
            properties: [
                'name' => 'Alice',
                'createdAt' => new DateTimeImmutable('2026-05-15'),
                'failedAt' => new DateTime('2026-05-15'),
            ],
        );

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertSame(['name'], array_map(static fn ($f) => $f->property, $fields));
    }

    #[Test]
    public function keepsStringableObjects(): void
    {
        // Stringable objects have __toString(), so the generic
        // template's `{{ value }}` is safe — keep them.
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'rendered';
            }
        };

        $record = new DataRecord(
            identifier: 'r-1',
            properties: ['name' => 'Alice', 'descriptor' => $stringable],
        );

        $fields = ControllerSupport::synthesiseFieldsFromRecord($record);

        self::assertSame(['name', 'descriptor'], array_map(static fn ($f) => $f->property, $fields));
    }
}
