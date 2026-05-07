<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Core\Field\FieldDto;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Field\FieldTrait;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;

/**
 * Resource with a mix of unrestricted and permission-gated fields,
 * used by FieldPermissionEnforcementTest to verify that a field
 * marked with `setPermission('SECRET')` is hidden when the current
 * user lacks the matching permission.
 */
final class FieldPermissionTestResource extends AbstractResource
{
    public function __construct()
    {
        parent::__construct(new InMemoryDataSource([
            new DataRecord('1', [
                'name' => 'flag-a',
                'salary' => 4200,
            ]),
        ]));
    }

    public function getName(): string
    {
        return 'gated';
    }

    public function getLabel(): string
    {
        return 'Gated fields';
    }

    public function configureFields(string $page): iterable
    {
        yield TestField::new('name');
        // `setPermission('SECRET')` should hide the field when the
        // current user lacks the SECRET attribute. The bundle's
        // ControllerSupport::collectFields must honour this.
        yield TestField::new('salary')->setPermission('SECRET');
    }
}

/** Minimal concrete FieldInterface for tests — lives next to the resource so the test kernel autoloads it. */
final class TestField implements FieldInterface
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
}
