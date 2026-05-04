<?php

declare(strict_types=1);

namespace Polysource\Bundle\Attribute;

use Attribute;

/**
 * Marks a class as a Polysource Resource and auto-tags it with `polysource.resource`.
 *
 * Cf. ADR-005 — the attribute is a registration shortcut. The resource
 * slug is taken from `ResourceInterface::getName()` at runtime; the
 * attribute carries no metadata in v0.1.
 *
 *     #[AsResource]
 *     final class FailedMessageResource extends AbstractResource { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsResource
{
}
