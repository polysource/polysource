<?php

declare(strict_types=1);

namespace Polysource\Filter\Model;

/**
 * One active filter on a property — `(property, operator, values)`.
 *
 * Immutable value object. Lives inside a `FilterCollection`. The
 * pipeline (mapper / formatter / renderer) consumes criterions to
 * produce form data, chip text, or a rendered widget.
 *
 * `values` is always a list (positional array) — even single-value
 * filters wrap their value in a 1-tuple. This keeps the contract
 * uniform across `=` (1 value), `between` (2 values), `in` (n values).
 *
 * @see \Polysource\Filter\Model\FilterCollection
 * @see \Polysource\Filter\Pipeline\FilterMapperInterface
 */
final readonly class FilterCriterion
{
    /**
     * @param non-empty-string $property      The Resource property targeted (e.g. `createdAt`).
     * @param non-empty-string $operator      The applied operator (`=`, `>=`, `between`, `like`, `in`, …).
     * @param list<mixed>      $values        Positional values consumed by the operator.
     */
    public function __construct(
        public string $property,
        public string $operator,
        public array $values = [],
    ) {
        if ('' === $property) {
            throw new \InvalidArgumentException('Filter property cannot be empty.');
        }

        if ('' === $operator) {
            throw new \InvalidArgumentException('Filter operator cannot be empty.');
        }

        // Enforce list semantics — `array_is_list` rejects associative
        // arrays so `values` is always positional. Empty list is OK
        // (e.g. an "isNull" operator that takes no value).
        if (!array_is_list($values)) {
            throw new \InvalidArgumentException(
                \sprintf('Filter values must be a list (positional array), got an associative array for property "%s".', $property),
            );
        }
    }

    public function withOperator(string $operator): self
    {
        return new self($this->property, $operator, $this->values);
    }

    /**
     * @param list<mixed> $values
     */
    public function withValues(array $values): self
    {
        return new self($this->property, $this->operator, $values);
    }

    /**
     * Compare against another criterion by structural equality
     * (property + operator + values). Useful for testing replacement
     * vs append in a `FilterCollection::with()`.
     */
    public function equals(self $other): bool
    {
        return $this->property === $other->property
            && $this->operator === $other->operator
            && $this->values === $other->values;
    }
}
