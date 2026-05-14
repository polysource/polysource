<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Storage;

use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;

/**
 * Read+write storage for filter URL tokens.
 *
 * @since 0.5.0
 */
interface FilterUrlTokenStorageInterface
{
    public function save(FilterUrlToken $token): void;

    /**
     * Resolve a token back to its full record, or `null` when the
     * token doesn't exist (or was pruned).
     */
    public function find(string $token): ?FilterUrlToken;
}
