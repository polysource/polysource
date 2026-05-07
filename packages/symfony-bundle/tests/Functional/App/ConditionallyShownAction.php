<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Inline action whose visibility is controlled by a public static
 * flag flipped by the test. Stateless on the resource side.
 */
final class ConditionallyShownAction implements InlineActionInterface
{
    public static bool $shown = true;

    public function getName(): string
    {
        return 'conditional-action';
    }

    public function getLabel(): string
    {
        return 'Conditional';
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
        return self::$shown;
    }

    public function execute(DataRecord $record): ActionResult
    {
        return ActionResult::success('ok');
    }
}
