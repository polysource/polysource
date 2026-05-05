<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use ValueError;

#[CoversClass(SavedViewScope::class)]
final class SavedViewScopeTest extends TestCase
{
    #[Test]
    public function exposesThreeCases(): void
    {
        $cases = SavedViewScope::cases();
        self::assertCount(3, $cases);
    }

    #[Test]
    public function caseValuesAreStableLowerCaseStrings(): void
    {
        self::assertSame('private', SavedViewScope::PRIVATE->value);
        self::assertSame('team', SavedViewScope::TEAM->value);
        self::assertSame('public', SavedViewScope::PUBLIC->value);
    }

    #[Test]
    public function fromStringRoundtripsEachCase(): void
    {
        foreach (SavedViewScope::cases() as $case) {
            self::assertSame($case, SavedViewScope::from($case->value));
        }
    }

    #[Test]
    public function fromUnknownStringThrows(): void
    {
        $this->expectException(ValueError::class);
        SavedViewScope::from('shared'); // not a valid case
    }
}
