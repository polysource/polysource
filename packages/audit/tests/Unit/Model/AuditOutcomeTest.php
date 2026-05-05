<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Polysource\Audit\Model\AuditOutcome;

/**
 * Lock the enum surface — case names + backing values are part of the
 * persisted contract (DB column + JSON export). A rename in the enum
 * would silently break audit history readability across versions.
 */
final class AuditOutcomeTest extends TestCase
{
    public function testEnumExposesExactlyThreeCases(): void
    {
        self::assertCount(3, AuditOutcome::cases());
    }

    public function testCaseValuesMatchPersistedContract(): void
    {
        self::assertSame('success', AuditOutcome::Success->value);
        self::assertSame('failure', AuditOutcome::Failure->value);
        self::assertSame('exception', AuditOutcome::Exception->value);
    }

    public function testTryFromAcceptsAllValidValues(): void
    {
        self::assertSame(AuditOutcome::Success, AuditOutcome::tryFrom('success'));
        self::assertSame(AuditOutcome::Failure, AuditOutcome::tryFrom('failure'));
        self::assertSame(AuditOutcome::Exception, AuditOutcome::tryFrom('exception'));
    }

    public function testTryFromRejectsUnknownValue(): void
    {
        self::assertNull(AuditOutcome::tryFrom('SUCCESS'));
        self::assertNull(AuditOutcome::tryFrom('skipped'));
        self::assertNull(AuditOutcome::tryFrom(''));
    }
}
