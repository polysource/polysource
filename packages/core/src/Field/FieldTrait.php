<?php

declare(strict_types=1);

namespace Polysource\Core\Field;

/**
 * Default fluent implementation of {@see FieldInterface}.
 *
 * Concrete field types use this trait + 1 static factory method:
 *
 *   final class TextField implements FieldInterface
 *   {
 *       use FieldTrait;
 *
 *       public static function new(string $property, ?string $label = null): self
 *       {
 *           return (new self($property, $label))->setTemplate('field/text.html.twig');
 *       }
 *   }
 */
trait FieldTrait
{
    private string $property;
    private ?string $label;
    private ?string $template = null;
    private ?string $permission = null;
    private bool $sortable = false;

    /** @var list<string> */
    private array $pages = ['index', 'detail', 'edit', 'new'];

    /** @var array<string, mixed> */
    private array $customOptions = [];

    public function __construct(string $property, ?string $label = null)
    {
        $this->property = $property;
        $this->label = $label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function setTemplate(string $template): static
    {
        $this->template = $template;

        return $this;
    }

    public function setPermission(?string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function setSortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    /** @param list<string> $pages */
    public function onPages(array $pages): static
    {
        $this->pages = array_values($pages);

        return $this;
    }

    public function onlyOnIndex(): static
    {
        return $this->onPages(['index']);
    }

    public function onlyOnDetail(): static
    {
        return $this->onPages(['detail']);
    }

    public function onlyOnForms(): static
    {
        return $this->onPages(['edit', 'new']);
    }

    public function hideOnIndex(): static
    {
        $this->pages = array_values(array_filter($this->pages, static fn (string $p): bool => 'index' !== $p));

        return $this;
    }

    public function setCustomOption(string $name, mixed $value): static
    {
        $this->customOptions[$name] = $value;

        return $this;
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
}
