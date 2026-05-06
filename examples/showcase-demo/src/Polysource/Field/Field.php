<?php

declare(strict_types=1);

namespace App\Polysource\Field;

use Polysource\Core\Field\FieldDto;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;

/**
 * Concrete field for the showcase. v0.1 of polysource/core ships only
 * the abstract FieldInterface + FieldTrait — concrete TextField /
 * DateTimeField / CodeField / BadgeField land in v0.2 (cf. ADR-011).
 *
 * This class fills the v0.1 gap with a single fluent type that maps
 * to the 6 templates already shipped by polysource/twig-theme:
 *
 *   Field::new('name')->asText()
 *   Field::new('failed_at')->asDateTime()
 *   Field::new('payload')->asCode()
 *   Field::new('id')->asId()
 *   Field::new('isActive')->asBoolean()
 *   Field::new('whatever')               // → generic.html.twig
 *
 * Hosts shipping their own field-types pre-v0.2 follow this pattern;
 * upstream concrete fields will keep the same `Field::new('p')->setX()`
 * fluent shape so a swap is a sed away.
 */
final class Field implements FieldInterface
{
    use FieldTrait;

    public static function new(string $property, ?string $label = null): self
    {
        return new self($property, $label);
    }

    public function getAsDto(): FieldDto
    {
        return new FieldDto(
            property: $this->property,
            label: $this->label,
            template: $this->template,
            permission: $this->permission,
            sortable: $this->sortable,
            pages: $this->pages,
            customOptions: $this->customOptions,
        );
    }

    public function asText(): static
    {
        return $this->setTemplate('@Polysource/field/text.html.twig');
    }

    public function asDateTime(): static
    {
        return $this->setTemplate('@Polysource/field/datetime.html.twig');
    }

    public function asCode(): static
    {
        return $this->setTemplate('@Polysource/field/code.html.twig');
    }

    public function asId(): static
    {
        return $this->setTemplate('@Polysource/field/id.html.twig');
    }

    public function asBoolean(): static
    {
        return $this->setTemplate('@Polysource/field/boolean.html.twig');
    }
}
