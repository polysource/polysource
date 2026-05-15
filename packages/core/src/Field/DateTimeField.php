<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * DateTime field — accepts ISO 8601 strings, `DateTimeInterface`
 * instances, or Unix timestamps. Rendering format is locale-aware.
 *
 * Backed by `@Polysource/field/datetime.html.twig` from `polysource/twig-theme`.
 *
 * @since 0.7.1
 */
final class DateTimeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/datetime.html.twig');
    }
}
