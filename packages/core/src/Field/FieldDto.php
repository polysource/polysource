<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Configuration snapshot of a {@see FieldInterface} for use by the UI layer.
 *
 * This is an immutable read model. The {@see FieldTrait} mutates a builder
 * internally, then materialises it as a FieldDto via {@see FieldInterface::getAsDto()}.
 */
final readonly class FieldDto
{
    /**
     * @param list<string>         $pages         pages where this field is displayed: 'index'|'detail'|'edit'|'new'
     * @param array<string, mixed> $customOptions adapter / theme-specific options
     */
    public function __construct(
        public string $property,
        public ?string $label = null,
        public ?string $template = null,
        public ?string $permission = null,
        public bool $sortable = false,
        public array $pages = ['index', 'detail', 'edit', 'new'],
        public array $customOptions = [],
    ) {
    }

    public function isOnPage(string $page): bool
    {
        return \in_array($page, $this->pages, true);
    }
}
