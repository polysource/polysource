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
 * **Allowed value types**: scalars (string, int, float, bool, null) and
 * nested arrays of scalars. Objects (`DateTimeImmutable`, etc.) are
 * intentionally NOT supported — they would defeat structural equality
 * via `equals()` and break session serialisation in `FilterService`.
 * Mappers must convert their domain types to plain strings/numbers
 * before constructing a criterion (e.g. format dates as ISO 8601).
 *
 * @see \Polysource\Filter\Model\FilterCollection
 * @see \Polysource\Filter\Pipeline\FilterMapperInterface
 */
final readonly class FilterCriterion
{
    /**
     * @param string      $property The Resource property targeted (e.g. `createdAt`). Must be non-empty (validated at runtime).
     * @param string      $operator The applied operator (`=`, `>=`, `between`, `like`, `in`, …). Must be non-empty (validated at runtime).
     * @param list<mixed> $values   Positional values consumed by the operator.
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
