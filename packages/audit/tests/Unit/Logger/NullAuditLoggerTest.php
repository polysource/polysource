<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Polysource\Audit\Logger\NullAuditLogger;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;

final class NullAuditLoggerTest extends TestCase
{
    public function testLogReturnsVoidAndDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        $logger = new NullAuditLogger();
        $entry = AuditEntry::nowFor(
            id: '01HF000000000000000000NULL',
            actorId: AuditEntry::ANONYMOUS_ACTOR_ID,
            actorLabel: null,
            resourceName: 'orders',
            actionName: 'retry',
            outcome: AuditOutcome::Success,
        );

        $logger->log($entry);

        // The contract for NullAuditLogger is precisely "do nothing
        // visibly" — passing means the call returned without throwing.
    }
}
