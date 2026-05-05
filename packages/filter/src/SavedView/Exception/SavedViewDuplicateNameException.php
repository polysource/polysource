<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Exception;

use RuntimeException;

/**
 * Thrown by {@see \Polysource\Filter\SavedView\SavedViewService::save()}
 * when the user tries to save a view whose `(ownerId, resourceName, name)`
 * triple already exists. Names must be unique per user per resource — a
 * user shouldn't have two views called "Daily inbox" on the same
 * Resource since the dropdown can't disambiguate them.
 *
 * Different users CAN have views with the same name; the conflict
 * is per-user only.
 *
 * @since 0.1.0
 */
final class SavedViewDuplicateNameException extends RuntimeException
{
    public function __construct(
        public readonly string $name,
        public readonly string $resourceName,
        public readonly string $ownerId,
    ) {
        parent::__construct(\sprintf(
            'A saved view named "%s" already exists for user "%s" on resource "%s".',
            $name,
            $ownerId,
            $resourceName,
        ));
    }
}
