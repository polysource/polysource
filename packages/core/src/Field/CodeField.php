<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Code field — renders monospace-formatted content (JSON payloads,
 * serialized session data, log lines, …).
 *
 * Backed by `@Polysource/field/code.html.twig` from `polysource/twig-theme`.
 *
 * @since 0.7.1
 */
final class CodeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/code.html.twig');
    }
}
