<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Registry;

use Polysource\Filter\Pipeline\FilterFormatterInterface;

/**
 * O(1) lookup for `FilterFormatterInterface` services by filter `name`.
 *
 * @see MapperRegistry — same wiring pattern, different interface.
 */
final class FormatterRegistry
{
    /** @var array<string, FilterFormatterInterface> */
    private readonly array $byName;

    /**
     * @param iterable<FilterFormatterInterface> $formatters
     * @param list<string>                       $knownNames
     */
    public function __construct(iterable $formatters, array $knownNames)
    {
        $byName = [];
        foreach ($formatters as $formatter) {
            foreach ($knownNames as $name) {
                if ($formatter->supports($name)) {
                    $byName[$name] ??= $formatter;
                }
            }
        }
        $this->byName = $byName;
    }

    public function forName(string $name): FilterFormatterInterface
    {
        if (!isset($this->byName[$name])) {
            throw new \RuntimeException(\sprintf(
                'No FilterFormatterInterface service supports filter name "%s".',
                $name,
            ));
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
