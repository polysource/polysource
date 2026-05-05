<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Filter;

use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\Core\Filter\FilterDto;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;

/**
 * Generic filter declaration used by every property the bulk job
 * dashboard exposes (actorId, status, createdAt, resourceName).
 *
 * Mirrors {@see \Polysource\Audit\Filter\AuditLogFilter} — a single
 * configurable class is enough because the actual translation into a
 * Doctrine query lives in
 * {@see \Polysource\BulkAsync\DataSource\BulkJobDataSource}. Fewer
 * classes, lower API surface (cf. ADR-010).
 */
final class BulkJobFilter implements FilterInterface
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

    /**
     * @return list<string>
     */
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

    public static function actorId(string $label = 'Actor'): self
    {
        return new self('actorId', $label, ['eq']);
    }

    public static function status(string $label = 'Status'): self
    {
        $choices = array_map(static fn (BulkJobStatus $s): string => $s->value, BulkJobStatus::cases());

        return new self(
            property: 'status',
            label: $label,
            supportedOperators: ['in'],
            customOptions: ['choices' => $choices],
        );
    }

    public static function createdAt(string $label = 'Created at'): self
    {
        return new self('createdAt', $label, ['between', 'gte', 'lte']);
    }

    public static function resourceName(string $label = 'Resource'): self
    {
        return new self('resourceName', $label, ['in']);
    }
}
