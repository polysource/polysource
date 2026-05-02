<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Declaration of a field rendered in admin pages (index/detail/edit/new).
 *
 * Use {@see FieldTrait} to implement this interface in 5 lines.
 *
 * Fields don't carry the *value* of the property — they describe how to
 * render it. The actual value comes from a {@see \Polysource\Core\Query\DataRecord}
 * via its property name.
 */
interface FieldInterface
{
    /**
     * Factory for fluent declarations: TextField::new('name')->setLabel('Name').
     */
    public static function new(string $property, ?string $label = null): self;

    /**
     * Snapshot of the field configuration as a {@see FieldDto}.
     */
    public function getAsDto(): FieldDto;
}
