<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Action;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Throwable;

/**
 * Acks a single failed envelope without retrying it — effectively
 * deletes the message from the failure transport.
 */
final class DismissFailedMessageAction implements InlineActionInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ListableReceiverInterface $failedReceiver,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getName(): string
    {
        return 'dismiss';
    }

    public function getLabel(): string
    {
        return 'Dismiss';
    }

    public function getIcon(): string
    {
        return 'trash';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_FAILED_MESSAGE_DISMISS';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function execute(DataRecord $record): ActionResult
    {
        $envelope = $record->rawSource;
        if (!$envelope instanceof Envelope) {
            return ActionResult::failure(\sprintf('Cannot dismiss record "%s": missing envelope.', $record->identifier));
        }

        try {
            $this->failedReceiver->ack($envelope);

            return ActionResult::success(\sprintf('Message %s dismissed.', $record->identifier));
        } catch (Throwable $e) {
            $this->logger->error('Polysource: failed to dismiss envelope', [
                'envelope_id' => $record->identifier,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return ActionResult::failure(\sprintf('Dismiss of message %s failed.', $record->identifier));
        }
    }
}
