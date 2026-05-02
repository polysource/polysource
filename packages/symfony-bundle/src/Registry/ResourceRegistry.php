<?php

declare(strict_types=1);

namespace Polysource\Bundle\Registry;

use LogicException;
use Polysource\Core\Exception\ResourceNotFoundException;
use Polysource\Core\Resource\ResourceInterface;

/**
 * Runtime lookup of {@see ResourceInterface} services tagged `polysource.resource`.
 *
 * Indexes resources lazily by their `getName()` slug on first access.
 * Throws on duplicate slugs (uniqueness invariant — cf. ADR-003 §Mitigation).
 */
final class ResourceRegistry
{
    /**
     * @var array<string, ResourceInterface>|null
     */
    private ?array $byName = null;

    /**
     * @param iterable<ResourceInterface> $resources tagged_iterator('polysource.resource')
     */
    public function __construct(
        private readonly iterable $resources,
    ) {
    }

    public function get(string $resourceName): ResourceInterface
    {
        $this->index();
        \assert(null !== $this->byName);

        if (!isset($this->byName[$resourceName])) {
            throw new ResourceNotFoundException(\sprintf('Polysource resource "%s" is not registered.', $resourceName));
        }

        return $this->byName[$resourceName];
    }

    public function has(string $resourceName): bool
    {
        $this->index();
        \assert(null !== $this->byName);

        return isset($this->byName[$resourceName]);
    }

    /**
     * @return array<string, ResourceInterface> map slug => resource
     */
    public function all(): array
    {
        $this->index();
        \assert(null !== $this->byName);

        return $this->byName;
    }

    private function index(): void
    {
        if (null !== $this->byName) {
            return;
        }

        $byName = [];
        foreach ($this->resources as $resource) {
            $name = $resource->getName();
            if (isset($byName[$name])) {
                throw new LogicException(\sprintf('Duplicate Polysource resource name "%s". Each ResourceInterface::getName() must be unique.', $name));
            }
            $byName[$name] = $resource;
        }

        $this->byName = $byName;
    }
}
