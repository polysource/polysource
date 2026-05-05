<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Event;

use Polysource\BulkAsync\Job\BulkJob;

/**
 * Internal event dispatched by
 * {@see \Polysource\BulkAsync\Messenger\BulkJobHandler} every time
 * the worker persists a progress flush — and once more on the
 * terminal flush.
 *
 * Subscribers (e.g. {@see \Polysource\BulkAsync\Mercure\MercureBulkJobBroadcaster})
 * use this hook to broadcast progress live without waiting for the
 * polling controller to be hit.
 *
 * The job carried here is the {@see BulkJob} that was just persisted
 * — listeners get the canonical post-flush state.
 */
final class BulkJobProgressEvent
{
    public function __construct(
        public readonly BulkJob $job,
    ) {
    }
}
