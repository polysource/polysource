<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Action;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Throwable;

/**
 * Re-dispatches a single failed envelope through the message bus.
 *
 * Strips `SentToFailureTransportStamp`, `ReceivedStamp` and
 * `TransportMessageIdStamp` so the message routes normally instead of
 * looping back to the failure transport.
 */
final class RetryFailedMessageAction implements InlineActionInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly ListableReceiverInterface $failedReceiver,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getName(): string
    {
        return 'retry';
    }

    public function getLabel(): string
    {
        return 'Retry';
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

    public function execute(DataRecord $record): ActionResult
    {
        $envelope = $record->rawSource;
        if (!$envelope instanceof Envelope) {
            return ActionResult::failure(\sprintf('Cannot retry record "%s": missing envelope.', $record->identifier));
        }

        try {
            $reborn = $envelope
                ->withoutAll(SentToFailureTransportStamp::class)
                ->withoutAll(ReceivedStamp::class)
                ->withoutAll(TransportMessageIdStamp::class)
            ;
            $this->bus->dispatch($reborn);
            $this->failedReceiver->ack($envelope);

            return ActionResult::success(\sprintf('Message %s queued for retry.', $record->identifier));
        } catch (Throwable $e) {
            $this->logger->error('Polysource: failed to retry envelope', [
                'envelope_id' => $record->identifier,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return ActionResult::failure(\sprintf('Retry of message %s failed.', $record->identifier));
        }
    }
}
