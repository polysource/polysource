<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Registry;

use Polysource\Filter\Pipeline\FilterMapperInterface;
use RuntimeException;

/**
 * O(1) lookup for `FilterMapperInterface` services by filter `name`.
 *
 * Built at compile-time by `PipelineCompilerPass`: it iterates every
 * service tagged `polysource.filter.mapper`, calls `supports()` against
 * known names, and indexes them. Hosts that need a custom mapper
 * register a service tagged `polysource.filter.mapper` that returns
 * `true` for their custom name; auto-discovery picks it up.
 */
final class MapperRegistry
{
    /** @var array<string, FilterMapperInterface> */
    private readonly array $byName;

    /**
     * @param iterable<FilterMapperInterface> $mappers Services tagged polysource.filter.mapper
     * @param list<string>                    $knownNames Names to probe each mapper against
     */
    public function __construct(iterable $mappers, array $knownNames)
    {
        $byName = [];
        foreach ($mappers as $mapper) {
            foreach ($knownNames as $name) {
                if ($mapper->supports($name)) {
                    $byName[$name] ??= $mapper;
                }
            }
        }
        $this->byName = $byName;
    }

    public function forName(string $name): FilterMapperInterface
    {
        if (!isset($this->byName[$name])) {
            throw new RuntimeException(\sprintf('No FilterMapperInterface service supports filter name "%s". Tag a service `polysource.filter.mapper` whose supports() returns true for this name.', $name));
        }

        return $this->byName[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->byName[$name]);
    }

    /**
     * @return list<string>
     */
    public function getKnownNames(): array
    {
        return array_keys($this->byName);
    }
}
