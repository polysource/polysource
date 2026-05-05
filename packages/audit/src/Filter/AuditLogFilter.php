<?php

declare(strict_types=1);

namespace Polysource\Audit\Filter;

use Polysource\Core\Filter\FilterDto;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;

/**
 * Generic filter declaration used by every property the audit log
 * exposes (occurredAt, actorId, resourceName, actionName, outcome).
 *
 * Why a single class for all 5: filters here carry no behaviour
 * beyond "this property accepts these operators". The actual query
 * translation lives in {@see \Polysource\Audit\DataSource\AuditLogDataSource}
 * which is responsible for mapping each `FilterCriterion` to a
 * Doctrine query builder clause. So a single configurable class
 * is enough — saves us a 5-class boilerplate burst per ADR-010
 * (core API surface budget).
 */
final class AuditLogFilter implements FilterInterface
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

    /* ---------------------------------------------------------------
     * Named constructors for the 5 standard audit log filters.
     *
     * Each baked-in label is in English; hosts override via the
     * resource subclass if they need translations.
     * --------------------------------------------------------------- */

    public static function occurredAt(string $label = 'Occurred at'): self
    {
        return new self('occurredAt', $label, ['between', 'gte', 'lte']);
    }

    public static function actorId(string $label = 'Actor'): self
    {
        return new self('actorId', $label, ['eq']);
    }

    public static function resourceName(string $label = 'Resource'): self
    {
        return new self('resourceName', $label, ['in']);
    }

    public static function actionName(string $label = 'Action'): self
    {
        return new self('actionName', $label, ['in']);
    }

    public static function outcome(string $label = 'Outcome'): self
    {
        return new self(
            property: 'outcome',
            label: $label,
            supportedOperators: ['in'],
            customOptions: ['choices' => ['success', 'failure', 'exception']],
        );
    }
}
