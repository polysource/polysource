<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Input payload for {@see \Polysource\Core\DataSource\WritableDataSourceInterface::create()}
 * and {@see \Polysource\Core\DataSource\WritableDataSourceInterface::update()}.
 *
 * Carries the raw form/API data — adapters decide how to map keys to their
 * native model (Doctrine entity setter, Redis HMSET, HTTP POST body, ...).
 */
final readonly class DataPayload
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public array $properties,
    ) {
    }

    public function get(string $property, mixed $default = null): mixed
    {
        return $this->properties[$property] ?? $default;
    }

    public function has(string $property): bool
    {
        return \array_key_exists($property, $this->properties);
    }

    public function with(string $property, mixed $value): self
    {
        $properties = $this->properties;
        $properties[$property] = $value;

        return new self($properties);
    }

    public function without(string $property): self
    {
        $properties = $this->properties;
        unset($properties[$property]);

        return new self($properties);
    }
}
