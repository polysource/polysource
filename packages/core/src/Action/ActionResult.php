<?php

declare(strict_types=1);

namespace Polysource\Core\Action;

/**
 * Result of executing an {@see ActionInterface}.
 *
 * Use the static factories rather than calling the constructor directly:
 *   - ActionResult::success('Done')
 *   - ActionResult::failure('Connection lost')
 *
 * Cf. ADR architecture — actions can also be asynchronous (the action
 * dispatches a Messenger message, the result reports "scheduled" success).
 */
final readonly class ActionResult
{
    /**
     * @param array<string, mixed> $context arbitrary metadata for logging / audit / retry
     */
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function success(?string $message = null, array $context = []): self
    {
        return new self(true, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function failure(string $message, array $context = []): self
    {
        return new self(false, $message, $context);
    }
}
