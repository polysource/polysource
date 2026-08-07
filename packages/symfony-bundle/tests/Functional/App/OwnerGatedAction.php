<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Inline action guarded by the `RECORD_OWNER` attribute. The kernel
 * wires {@see RecordSubjectPermission}, which only grants that
 * attribute when the voter subject is a record owned by `me` — so
 * this action's button (and its POST endpoint) must be gated per
 * record, not per resource.
 */
final class OwnerGatedAction implements InlineActionInterface
{
    public function getName(): string
    {
        return 'owner-gated';
    }

    public function getLabel(): string
    {
        return 'Owner gated';
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getPermission(): string
    {
        return 'RECORD_OWNER';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function execute(DataRecord $record): ActionResult
    {
        return ActionResult::success('ok');
    }
}
