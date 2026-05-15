<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Boolean field — renders true/false as Bootstrap-themed badge.
 *
 * Backed by `@Polysource/field/boolean.html.twig` from `polysource/twig-theme`.
 *
 * @since 0.7.1
 */
final class BooleanField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/boolean.html.twig');
    }
}
