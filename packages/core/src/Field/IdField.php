<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Identifier field — renders monospace with a copy-friendly affordance.
 *
 * Backed by `@Polysource/field/id.html.twig` from `polysource/twig-theme`.
 *
 * @since 0.7.1
 */
final class IdField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/id.html.twig');
    }
}
