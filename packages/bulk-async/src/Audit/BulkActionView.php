<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Audit;

use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Adapter wrapping the original {@see BulkActionInterface} so the
 * audit subscriber sees the async run as a distinct action named
 * `bulk:<original-name>` (cf. ADR-024 §10).
 *
 * The wrapper is *only* used to ferry the original action's identity
 * + permission into {@see \Polysource\Bundle\Event\ActionExecutedEvent};
 * its `executeBatch()` is a deliberate no-op that returns success.
 * The real execution happened upstream inside
 * {@see \Polysource\BulkAsync\Messenger\BulkJobHandler::processRecords()}.
 */
final class BulkActionView implements BulkActionInterface
{
    public function __construct(
        private readonly BulkActionInterface $original,
    ) {
    }

    public function getOriginal(): BulkActionInterface
    {
        return $this->original;
    }

    public function getName(): string
    {
        return 'bulk:' . $this->original->getName();
    }

    public function getLabel(): string
    {
        return $this->original->getLabel();
    }

    public function getIcon(): ?string
    {
        return $this->original->getIcon();
    }

    public function getPermission(): ?string
    {
        return $this->original->getPermission();
    }

    public function isDisplayed(array $context = []): bool
    {
        return $this->original->isDisplayed($context);
    }

    /**
     * No-op — see class docblock.
     *
     * @param iterable<DataRecord> $records
     */
    public function executeBatch(iterable $records): ActionResult
    {
        unset($records);

        return ActionResult::success();
    }

    public static function isViewOf(ActionInterface $action, BulkActionInterface $original): bool
    {
        return $action instanceof self && $action->getOriginal() === $original;
    }
}
