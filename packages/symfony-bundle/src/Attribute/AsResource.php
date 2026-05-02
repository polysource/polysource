<?php

declare(strict_types=1);

namespace Polysource\Bundle\Attribute;

use Attribute;

/**
 * Marks a class as a Polysource Resource and auto-tags it with `polysource.resource`.
 *
 * Cf. ADR-005 — the attribute is a registration shortcut. It does NOT replace
 * {@see \Polysource\Core\Resource\ResourceInterface::getName()} — that method
 * remains the source of truth for the resource slug.
 *
 * The optional `name` argument is reserved for future use (compile-time
 * routing optimisation). It MUST match `getName()` if provided.
 *
 *     #[AsResource]
 *     final class FailedMessageResource extends AbstractResource { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsResource
{
    public function __construct(
        public ?string $name = null,
    ) {
    }
}
