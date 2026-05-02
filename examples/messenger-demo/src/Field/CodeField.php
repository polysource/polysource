<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Field;

use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

final class CodeField implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return (new self($property, $label))->setTemplate('@Polysource/field/code.html.twig');
    }
}
