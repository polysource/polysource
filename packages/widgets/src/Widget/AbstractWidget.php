<?php

declare(strict_types=1);

namespace Polysource\Widgets\Widget;

use InvalidArgumentException;

/**
 * Base class providing the common id/title/columnSpan plumbing.
 * Subclasses still implement `getTemplate()` + `getViewData()` —
 * the parts that vary per widget type.
 *
 * Validation:
 *  - id must be non-empty
 *  - title must be non-empty
 *  - columnSpan must be in [1..12] (Bootstrap grid)
 */
abstract class AbstractWidget implements WidgetInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly int $columnSpan = 4,
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('Widget id cannot be empty.');
        }
        if ('' === $title) {
            throw new InvalidArgumentException('Widget title cannot be empty.');
        }
        if ($columnSpan < 1 || $columnSpan > 12) {
            throw new InvalidArgumentException(\sprintf('Widget columnSpan must be 1..12, got %d.', $columnSpan));
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getColumnSpan(): int
    {
        return $this->columnSpan;
    }
}
