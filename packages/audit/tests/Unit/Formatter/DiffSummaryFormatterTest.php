<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Formatter\DiffSummaryFormatter;

#[CoversClass(DiffSummaryFormatter::class)]
final class DiffSummaryFormatterTest extends TestCase
{
    #[Test]
    public function createActionEmitsFieldEqualsValuePairs(): void
    {
        $summary = DiffSummaryFormatter::summarise(
            'create',
            ['name' => ['old' => null, 'new' => 'Widget'], 'price' => ['old' => null, 'new' => 99]],
            null,
        );

        self::assertSame("name='Widget', price=99", $summary);
    }

    #[Test]
    public function updateActionEmitsArrowFormat(): void
    {
        $summary = DiffSummaryFormatter::summarise(
            'update',
            ['name' => ['old' => 'Old', 'new' => 'New']],
            null,
        );

        self::assertSame("name: 'Old' → 'New'", $summary);
    }

    #[Test]
    public function deleteActionReadsFromSnapshotNotChanges(): void
    {
        $summary = DiffSummaryFormatter::summarise(
            'delete',
            null,
            ['name' => 'Doomed', 'email' => 'gone@example.com'],
        );

        self::assertSame("name='Doomed', email='gone@example.com'", $summary);
    }

    #[Test]
    public function returnsNullWhenChangesAreEmpty(): void
    {
        self::assertNull(DiffSummaryFormatter::summarise('update', null, null));
        self::assertNull(DiffSummaryFormatter::summarise('update', [], null));
    }

    #[Test]
    public function formatsScalarValuesConsistently(): void
    {
        self::assertSame('null', DiffSummaryFormatter::formatScalar(null));
        self::assertSame('true', DiffSummaryFormatter::formatScalar(true));
        self::assertSame('false', DiffSummaryFormatter::formatScalar(false));
        self::assertSame("'hello'", DiffSummaryFormatter::formatScalar('hello'));
        self::assertSame('42', DiffSummaryFormatter::formatScalar(42));
        self::assertSame('1.5', DiffSummaryFormatter::formatScalar(1.5));
        self::assertSame('array(3)', DiffSummaryFormatter::formatScalar([1, 2, 3]));
    }

    #[Test]
    public function truncatesLongMessages(): void
    {
        $longValue = str_repeat('a', DiffSummaryFormatter::MAX_MESSAGE_BYTES + 100);
        $summary = (string) DiffSummaryFormatter::summarise(
            'update',
            ['x' => ['old' => '', 'new' => $longValue]],
            null,
        );

        self::assertStringEndsWith('… [truncated]', $summary);
        self::assertLessThan(
            DiffSummaryFormatter::MAX_MESSAGE_BYTES + 30,
            \strlen($summary),
            'truncated output must stay within cap + suffix',
        );
    }
}
