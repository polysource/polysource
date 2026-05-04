<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Registry;

use Polysource\Filter\Pipeline\FilterRendererInterface;
use RuntimeException;

/**
 * O(1) lookup for `FilterRendererInterface` services by filter `name`.
 *
 * @see MapperRegistry — same wiring pattern, different interface.
 */
final class RendererRegistry
{
    /** @var array<string, FilterRendererInterface> */
    private readonly array $byName;

    /**
     * @param iterable<FilterRendererInterface> $renderers
     * @param list<string>                      $knownNames
     */
    public function __construct(iterable $renderers, array $knownNames)
    {
        $byName = [];
        foreach ($renderers as $renderer) {
            foreach ($knownNames as $name) {
                if ($renderer->supports($name)) {
                    $byName[$name] ??= $renderer;
                }
            }
        }
        $this->byName = $byName;
    }

    public function forName(string $name): FilterRendererInterface
    {
        if (!isset($this->byName[$name])) {
            throw new RuntimeException(\sprintf('No FilterRendererInterface service supports filter name "%s".', $name));
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
