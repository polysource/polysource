<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Mirrors the workflow-bridge `ApplyTransitionAction` contract:
 * invisible unless `$context['subject']` carries the domain object
 * (`DataRecord::$rawSource`). Regression fixture for the bug where
 * the controllers collected action views with an empty context and
 * every transition button vanished.
 */
final class SubjectRequiringAction implements InlineActionInterface
{
    public function getName(): string
    {
        return 'subject-required';
    }

    public function getLabel(): string
    {
        return 'Subject required';
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
        return \is_object($context['subject'] ?? null);
    }

    public function execute(DataRecord $record): ActionResult
    {
        return ActionResult::success('ok');
    }
}
