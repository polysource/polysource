<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Action;

use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Throwable;

/**
 * Acks every envelope on the failed transport — wipes the dashboard
 * without retrying anything.
 *
 * Like {@see RetryAllFailedMessagesAction}, ignores the `$records`
 * parameter and operates on the full transport. UI surface should
 * confirm with the user before submitting.
 */
final readonly class PurgeFailedMessagesAction implements BulkActionInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private ListableReceiverInterface $failedReceiver,
        private int $maxPurge = 1000,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getName(): string
    {
        return 'purge';
    }

    public function getLabel(): string
    {
        return 'Purge all';
    }

    public function getIcon(): string
    {
        return 'trash';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_FAILED_MESSAGE_PURGE';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function executeBatch(iterable $records): ActionResult
    {
        unset($records);

        $purged = 0;
        $failed = 0;
        foreach ($this->failedReceiver->all($this->maxPurge) as $envelope) {
            try {
                $this->failedReceiver->ack($envelope);
                ++$purged;
            } catch (Throwable $e) {
                $this->logger->error('Polysource: failed to ack envelope during purge', [
                    'envelope_id' => self::tryExtractId($envelope),
                    'exception_class' => $e::class,
                    'exception_message' => $e->getMessage(),
                ]);
                ++$failed;
            }
        }

        if ($failed > 0) {
            return ActionResult::failure(\sprintf('%d purged, %d failed (capped at %d).', $purged, $failed, $this->maxPurge));
        }

        return ActionResult::success(\sprintf('%d message%s purged.', $purged, 1 === $purged ? '' : 's'));
    }

    private static function tryExtractId(Envelope $envelope): string|int|null
    {
        try {
            return EnvelopeMapper::extractIdentifier($envelope);
        } catch (Throwable) {
            return null;
        }
    }
}
