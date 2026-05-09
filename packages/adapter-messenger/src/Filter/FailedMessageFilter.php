<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Filter;

use Polysource\Core\Filter\FilterDto;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;

/**
 * Generic filter declaration for the {@see \Polysource\Adapter\Messenger\Resource\FailedMessageResource}.
 *
 * Mirrors the {@see \Polysource\Audit\Filter\AuditLogFilter} pattern:
 * filters carry no behaviour beyond "this property accepts these
 * operators". The actual filtering happens client-side inside
 * {@see \Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource}
 * since Symfony's {@see \Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface}
 * does not expose any native filtering primitive — every listing
 * goes through `all($limit)` and we filter the resulting envelopes
 * in PHP after mapping.
 *
 * Acceptable performance trade-off: failed transports are usually
 * small (< 1k envelopes) and ops want the filters to triage
 * incidents, not to replace a query engine.
 */
final class FailedMessageFilter implements FilterInterface
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
     * Named constructors for the 3 properties the EnvelopeMapper exposes
     * with stable filterable semantics.
     * --------------------------------------------------------------- */

    public static function messageClass(string $label = 'Message class'): self
    {
        return new self('message_class', $label, ['eq', 'in', 'like']);
    }

    public static function exceptionClass(string $label = 'Exception'): self
    {
        return new self('exception_class', $label, ['eq', 'in']);
    }

    public static function failedAt(string $label = 'Failed at'): self
    {
        return new self('failed_at', $label, ['between', 'gte', 'lte']);
    }
}
