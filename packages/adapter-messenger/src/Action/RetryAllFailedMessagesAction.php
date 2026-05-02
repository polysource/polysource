<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Action;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Throwable;

/**
 * Retries every envelope present on the failed transport.
 *
 * Bulk action — but the `$records` parameter is intentionally ignored.
 * The action operates on the entire transport because the UI button has
 * no record selection ("Retry all"). The action enforces an internal
 * cap so a runaway transport with thousands of messages cannot DoS the
 * worker pool from a single click.
 */
final readonly class RetryAllFailedMessagesAction implements BulkActionInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ListableReceiverInterface $failedReceiver,
        private int $maxRetries = 1000,
    ) {
    }

    public function getName(): string
    {
        return 'retry-all';
    }

    public function getLabel(): string
    {
        return 'Retry all';
    }

    public function getIcon(): string
    {
        return 'arrow-clockwise';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_FAILED_MESSAGE_RETRY';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function executeBatch(iterable $records): ActionResult
    {
        unset($records); // intentional — operates on the entire transport

        $retried = 0;
        $failed = 0;
        foreach ($this->failedReceiver->all($this->maxRetries) as $envelope) {
            try {
                $reborn = $envelope
                    ->withoutAll(SentToFailureTransportStamp::class)
                    ->withoutAll(ReceivedStamp::class)
                    ->withoutAll(TransportMessageIdStamp::class)
                ;
                $this->bus->dispatch($reborn);
                $this->failedReceiver->ack($envelope);
                ++$retried;
            } catch (Throwable) {
                ++$failed;
            }
        }

        if ($failed > 0) {
            return ActionResult::failure(\sprintf('%d retried, %d failed (capped at %d).', $retried, $failed, $this->maxRetries));
        }

        return ActionResult::success(\sprintf('%d message%s queued for retry.', $retried, 1 === $retried ? '' : 's'));
    }
}
