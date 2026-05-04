<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Bridge;

use BadMethodCallException;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Fluent decorator over an EA `FieldInterface` exposing the
 * bridge's custom-option API on the field side — symmetric to
 * {@see PolysourceFilter}.
 *
 * Primary use case: `chipFormatter()` on a field so the chip
 * rendering uses the SAME callable as the table column display.
 *
 * Usage:
 *
 *     yield Polysource::field(BooleanField::new('isVisible'))
 *         ->chipFormatter(fn ($v) => $v ? 'Actif' : 'Inactif')
 *         ->meta('export_format', 'yn');
 *
 * Implements `FieldInterface` so EA's `configureFields()` accepts
 * it. `getAsDto()` returns the wrapped field's DTO (with our
 * customOptions written on it).
 */
final class PolysourceField implements FieldInterface
{
    public static function on(FieldInterface $field): self
    {
        return new self($field);
    }

    private function __construct(private FieldInterface $field)
    {
    }

    /**
     * @param callable(mixed): string $callable
     */
    public function chipFormatter(callable $callable): self
    {
        $this->field->getAsDto()->setCustomOption(BridgeOptions::CHIP_FORMATTER, $callable);

        return $this;
    }

    public function meta(string $key, mixed $value): self
    {
        $this->field->getAsDto()->setCustomOption($key, $value);

        return $this;
    }

    public static function new(string $propertyName, TranslatableInterface|string|bool|null $label = null): self
    {
        // Required by FieldInterface but never called on the proxy
        // — hosts always start from a real EA field type
        // (BooleanField::new(), TextField::new(), …) then wrap.
        throw new BadMethodCallException('PolysourceField::new() is not a constructor — wrap an existing FieldInterface instead via Polysource::field($field).');
    }

    public function getAsDto(): FieldDto
    {
        return $this->field->getAsDto();
    }

    public function __clone(): void
    {
        $this->field = clone $this->field;
    }
}
