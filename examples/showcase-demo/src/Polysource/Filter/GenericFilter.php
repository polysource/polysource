<?php

declare(strict_types=1);

namespace App\Polysource\Filter;

use Polysource\Core\Filter\FilterDto;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;

/**
 * Tiny generic filter declaration usable by any showcase adapter
 * resource that doesn't ship its own Filter type.
 *
 * Borrowed from `polysource/audit::AuditLogFilter` and
 * `App\Polysource\Filter\LoginAttemptFilter`. Carries no logic
 * beyond "this property accepts these operators" — the DataSource
 * is responsible for translating the resulting `FilterCriterion`
 * into its native query language (Redis SCAN MATCH, Flysystem
 * filter-by-suffix, HTTP query string, Meilisearch filter
 * expression). Adapters that ignore the criterion silently no-op,
 * which is fine for the showcase: the modal still renders the
 * filter UI even when the backend can't honour it.
 */
final class GenericFilter implements FilterInterface
{
    /**
     * @param list<string>         $supportedOperators
     * @param array<string, mixed> $customOptions
     */
    public function __construct(
        private readonly string $property,
        private readonly string $label,
        private readonly array $supportedOperators,
        private readonly array $customOptions = [],
    ) {
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return list<string> */
    public function getSupportedOperators(): array
    {
        return $this->supportedOperators;
    }

    public function applyToQuery(DataQuery $query, FilterCriterion $criterion): DataQuery
    {
        return $query->withFilter($this->property, $criterion);
    }

    public function getAsDto(): FilterDto
    {
        return new FilterDto(
            property: $this->property,
            label: $this->label,
            supportedOperators: $this->supportedOperators,
            customOptions: $this->customOptions,
        );
    }

    public static function text(string $property, string $label): self
    {
        return new self($property, $label, ['like', 'eq']);
    }

    public static function exact(string $property, string $label): self
    {
        return new self($property, $label, ['eq', 'neq']);
    }

    public static function numeric(string $property, string $label): self
    {
        return new self($property, $label, ['eq', 'gt', 'gte', 'lt', 'lte']);
    }

    public static function date(string $property, string $label): self
    {
        return new self($property, $label, ['gte', 'lte', 'between']);
    }
}
