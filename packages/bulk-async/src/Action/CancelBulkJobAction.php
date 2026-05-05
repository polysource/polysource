<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Action;

use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Job\BulkJobStorageInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Inline action that flips a {@see BulkJob} into the
 * {@see BulkJobStatus::Cancelled} state.
 *
 * The handler ({@see \Polysource\BulkAsync\Messenger\BulkJobHandler})
 * checks the live status before each per-record iteration; flipping
 * to Cancelled here therefore stops the worker on its next tick
 * (cf. ADR-024 §5).
 *
 * Idempotent: cancelling an already-terminal job (Completed /
 * Failed / Cancelled) is a no-op success — operators may double-tap
 * Cancel without seeing an error.
 */
final class CancelBulkJobAction implements InlineActionInterface
{
    public const PERMISSION = 'POLYSOURCE_BULK_JOB_CANCEL';

    public function __construct(
        private readonly BulkJobStorageInterface $storage,
    ) {
    }

    public function getName(): string
    {
        return 'cancel';
    }

    public function getLabel(): string
    {
        return 'Cancel';
    }

    /**
     * @phpstan-ignore-next-line return.unusedType — interface contract is `?string`; we always know
     */
    public function getIcon(): ?string
    {
        return 'x-circle';
    }

    /**
     * @phpstan-ignore-next-line return.unusedType — interface contract is `?string`; we always know
     */
    public function getPermission(): ?string
    {
        return self::PERMISSION;
    }

    public function isDisplayed(array $context = []): bool
    {
        $record = $context['record'] ?? null;
        if (!$record instanceof DataRecord) {
            return true;
        }

        $status = $record->get('status');

        return BulkJobStatus::Pending->value === $status
            || BulkJobStatus::Running->value === $status;
    }

    public function execute(DataRecord $record): ActionResult
    {
        $job = $this->storage->find((string) $record->identifier);
        if (null === $job) {
            return ActionResult::failure(\sprintf('Bulk job "%s" no longer exists.', $record->identifier));
        }

        if ($job->status->isTerminal()) {
            return ActionResult::success(\sprintf('Bulk job already %s — nothing to cancel.', $job->status->value));
        }

        $this->storage->save($job->withStatus(BulkJobStatus::Cancelled));

        return ActionResult::success('Bulk job cancelled. The worker will stop on its next tick.');
    }
}
