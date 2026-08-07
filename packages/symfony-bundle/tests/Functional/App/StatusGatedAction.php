<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Inline action hidden on records whose `status` property is
 * `done` — exercises the per-record `isDisplayed()` context
 * (`record` key) introduced for row-aware gating.
 */
final class StatusGatedAction implements InlineActionInterface
{
    public function getName(): string
    {
        return 'status-gated';
    }

    public function getLabel(): string
    {
        return 'Status gated';
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getPermission(): ?string
    {
        return null;
    }

    public function isDisplayed(array $context = []): bool
    {
        $record = $context['record'] ?? null;
        if (!$record instanceof DataRecord) {
            // No row context → resource-level collection (bulk list,
            // legacy template fallback). Show by default.
            return true;
        }

        return 'done' !== $record->get('status');
    }

    public function execute(DataRecord $record): ActionResult
    {
        return ActionResult::success('ok');
    }
}
