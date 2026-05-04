<?php

declare(strict_types=1);

namespace Polysource\Filter\Model;

use InvalidArgumentException;

/**
 * Declarative spec for a filter that hosts can offer.
 *
 * A `FilterDefinition` is the *configuration* — what filters are
 * *available* on a Resource. A `FilterCriterion` is the *applied
 * value* — what the user actually picked. The two never get
 * confused.
 *
 * Codified: each definition carries TWO faces.
 *
 * - `formSpec` — consumed by the renderer (what FormType to use, what
 *   options to pass to it, label, placeholder, etc.).
 * - `datasourceSpec` — consumed by the applier (the columns to filter
 *   on, the operator default, value transformations). The shape is
 *   open-ended on purpose: a Doctrine applier reads `column` +
 *   `joins`; a Redis applier reads `prefix` + `decode_strategy`; etc.
 *
 * The pipeline (`mapper`/`formatter`/`renderer`) routes by the `name`
 * field — host-defined logical identifier that points at a triplet
 * of services tagged `polysource.filter.*`.
 *
 * Immutable. Build via the static `new()` factory + `with*()` setters.
 */
final readonly class FilterDefinition
{
    /**
     * @param string                       $name           Pipeline routing key (e.g. "datetime"). Must be non-empty (validated at runtime).
     * @param string                       $property       Resource property the filter targets. Must be non-empty (validated at runtime).
     * @param string                       $label          human label, can be empty (host falls back to property)
     * @param ?string                      $group          Optional group label for multi-group UI mode. Either null or non-empty (validated at runtime).
     * @param array<string, mixed>         $formSpec       form-side configuration (FormType FQCN, options, …)
     * @param array<string, mixed>         $datasourceSpec data-source-side configuration (columns, operator, …)
     */
    public function __construct(
        public string $name,
        public string $property,
        public string $label = '',
        public ?string $group = null,
        public array $formSpec = [],
        public array $datasourceSpec = [],
    ) {
        if ('' === $name) {
            throw new InvalidArgumentException('FilterDefinition name cannot be empty.');
        }

        if ('' === $property) {
            throw new InvalidArgumentException('FilterDefinition property cannot be empty.');
        }

        if (null !== $group && '' === $group) {
            throw new InvalidArgumentException('FilterDefinition group must be either null (no group) or a non-empty string.');
        }
    }

    /**
     * Convenience factory with sensible defaults — most filters only
     * need name + property + label, the rest defaults to empty.
     */
    public static function new(string $name, string $property, string $label = ''): self
    {
        return new self($name, $property, $label);
    }

    public function withLabel(string $label): self
    {
        return new self(
            $this->name,
            $this->property,
            $label,
            $this->group,
            $this->formSpec,
            $this->datasourceSpec,
        );
    }

    public function withGroup(?string $group): self
    {
        return new self(
            $this->name,
            $this->property,
            $this->label,
            $group,
            $this->formSpec,
            $this->datasourceSpec,
        );
    }

    /**
     * Replaces the entire `formSpec` (no merge with the existing one).
     * Hosts wanting partial updates should compose:
     *     `$d->withFormSpec([...$d->formSpec, 'key' => 'value'])`.
     *
     * @param array<string, mixed> $formSpec
     */
    public function withFormSpec(array $formSpec): self
    {
        return new self(
            $this->name,
            $this->property,
            $this->label,
            $this->group,
            $formSpec,
            $this->datasourceSpec,
        );
    }

    /**
     * Replaces the entire `datasourceSpec` (no merge). Same composition
     * pattern as `withFormSpec()` for partial updates.
     *
     * @param array<string, mixed> $datasourceSpec
     */
    public function withDatasourceSpec(array $datasourceSpec): self
    {
        return new self(
            $this->name,
            $this->property,
            $this->label,
            $this->group,
            $this->formSpec,
            $datasourceSpec,
        );
    }
}
