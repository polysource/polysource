<?php

declare(strict_types=1);

namespace App\Polysource\Filter;

use App\Entity\LoginAttempt;
use Polysource\Core\Filter\FilterDto;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;

/**
 * Filter declarations for {@see App\Polysource\Resource\LoginAttemptResource}.
 *
 * One class with named factories — keeps the resource configureFilters()
 * compact and the supported operators per property explicit. Pattern
 * borrowed from polysource/audit's AuditLogFilter.
 */
final class LoginAttemptFilter implements FilterInterface
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

    /** @return array<string, mixed> */
    public function getCustomOptions(): array
    {
        return $this->customOptions;
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

    public static function email(string $label = 'Email'): self
    {
        return new self('email', $label, ['eq', 'in']);
    }

    public static function ip(string $label = 'IP address'): self
    {
        return new self('ip', $label, ['eq', 'in']);
    }

    public static function status(string $label = 'Status'): self
    {
        return new self(
            property: 'status',
            label: $label,
            supportedOperators: ['in'],
            customOptions: ['choices' => LoginAttempt::statuses()],
        );
    }

    public static function occurredAt(string $label = 'Occurred at'): self
    {
        return new self('occurredAt', $label, ['between', 'gte', 'lte']);
    }
}
