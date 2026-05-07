<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;

/** Bulk action whose handler is a no-op — exercises cap + CSRF guards only. */
final class NoopBulkAction implements BulkActionInterface
{
    public function getName(): string
    {
        return 'noop-bulk';
    }

    public function getLabel(): string
    {
        return 'Noop';
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

    public function executeBatch(iterable $records): ActionResult
    {
        $count = 0;
        foreach ($records as $_) {
            ++$count;
        }

        return ActionResult::success(\sprintf('Processed %d', $count));
    }
}
