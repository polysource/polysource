<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

use InvalidArgumentException;

/**
 * Pagination parameters for a {@see DataQuery}.
 *
 * Supports both offset-based and cursor-based pagination:
 * - offset/limit: classic pagination, requires the source to know its total
 * - cursor: opaque token, used for sources that don't expose a total count
 *
 * Cf. ADR-002 for the rationale.
 */
final class Pagination
{
    public function __construct(
        public readonly int $offset = 0,
        public readonly int $limit = 20,
        public readonly ?string $cursor = null,
    ) {
        if ($offset < 0) {
            throw new InvalidArgumentException(\sprintf('Pagination offset must be >= 0, got %d.', $offset));
        }
        if ($limit < 1) {
            throw new InvalidArgumentException(\sprintf('Pagination limit must be >= 1, got %d.', $limit));
        }
    }

    public function withOffset(int $offset): self
    {
        return new self($offset, $this->limit, $this->cursor);
    }

    public function withLimit(int $limit): self
    {
        return new self($this->offset, $limit, $this->cursor);
    }

    public function withCursor(?string $cursor): self
    {
        return new self($this->offset, $this->limit, $cursor);
    }
}
