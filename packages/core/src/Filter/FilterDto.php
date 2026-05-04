<?php

declare(strict_types=1);

namespace Polysource\Core\Filter;

/**
 * Configuration snapshot of a {@see FilterInterface}.
 */
final class FilterDto
{
    /**
     * @param list<string>         $supportedOperators
     * @param array<string, mixed> $customOptions
     */
    public function __construct(
        public readonly string $property,
        public readonly string $label,
        public readonly array $supportedOperators,
        public readonly ?string $template = null,
        public readonly array $customOptions = [],
    ) {
    }
}
