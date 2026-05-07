<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;

final class AlwaysShownAction implements InlineActionInterface
{
    public function getName(): string
    {
        return 'always-shown-action';
    }

    public function getLabel(): string
    {
        return 'Always';
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
        return true;
    }

    public function execute(DataRecord $record): ActionResult
    {
        return ActionResult::success('ok');
    }
}
