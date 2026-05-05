<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Action;

use Polysource\Core\Action\BulkActionInterface;

/**
 * Opt-in marker that a {@see BulkActionInterface} prefers async
 * dispatch above a host-defined record threshold (cf. ADR-024 §6).
 *
 * Why a parallel interface, not a method on `BulkActionInterface`?
 * The core contract has been stable for several releases; adding
 * `isAsync()` would force every existing implementer to add a no-op
 * method. This package is opt-in, so its opt-in marker stays
 * opt-in too.
 *
 * Hosts wire actions implementing this interface through
 * {@see \Polysource\BulkAsync\Dispatcher\AsyncBulkActionDispatcher}
 * from their action controller — keeping the existing synchronous
 * path for actions that don't implement it.
 */
interface AsyncAwareBulkActionInterface extends BulkActionInterface
{
    /**
     * Returns true when the action wants the host to dispatch it
     * asynchronously for the given record count. Hosts that ignore
     * the hint can still dispatch sync; hosts that never wire async
     * see the marker as a no-op.
     */
    public function shouldRunAsync(int $recordCount): bool;
}
