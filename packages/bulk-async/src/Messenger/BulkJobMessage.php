<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Messenger;

use InvalidArgumentException;

/**
 * Messenger envelope payload pointing at one {@see \Polysource\BulkAsync\Job\BulkJob}.
 *
 * Carries only the job id — the worker re-loads the live row from
 * storage at handle time. This is intentional:
 *
 *  - Re-delivery safety: replays read the current status (and skip if
 *    no longer Pending) without us having to encode lifecycle state
 *    in the envelope.
 *  - Cancellation respect: the worker re-fetches the row each
 *    iteration; the message itself never holds stale `status`.
 *  - Tiny payload: 36 bytes on the wire keeps Messenger transport
 *    overhead negligible regardless of job size.
 */
final class BulkJobMessage
{
    public function __construct(
        public readonly string $jobId,
    ) {
        if ('' === $jobId) {
            throw new InvalidArgumentException('BulkJobMessage jobId cannot be empty.');
        }
    }
}
