<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Plain text field — renders the value as-is (HTML-escaped).
 *
 * Backed by `@Polysource/field/text.html.twig` from `polysource/twig-theme`.
 *
 * @since 0.7.1
 */
final class TextField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/text.html.twig');
    }
}
