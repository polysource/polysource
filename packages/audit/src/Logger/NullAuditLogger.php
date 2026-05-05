<?php

declare(strict_types=1);

namespace Polysource\Audit\Logger;

use Polysource\Audit\Model\AuditEntry;

/**
 * No-op logger — discards every entry. The default in test
 * environments where audit traffic would just pollute fixtures, and
 * a useful safety net for hosts that haven't wired an actual logger
 * yet (no audit is better than a 500 error from a missing service).
 */
final class NullAuditLogger implements AuditLoggerInterface
{
    public function log(AuditEntry $entry): void
    {
        // intentional no-op
    }
}
