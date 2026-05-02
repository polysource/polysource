<?php

declare(strict_types=1);

namespace Polysource\Core\Filter;

/**
 * Configuration snapshot of a {@see FilterInterface}.
 */
final readonly class FilterDto
{
    /**
     * @param list<string>         $supportedOperators
     * @param array<string, mixed> $customOptions
     */
    public function __construct(
        public string $property,
        public string $label,
        public array $supportedOperators,
        public ?string $template = null,
        public array $customOptions = [],
    ) {
    }
}
