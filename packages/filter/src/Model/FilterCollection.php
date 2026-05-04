<?php

declare(strict_types=1);

namespace Polysource\Filter\Model;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * An ordered list of `FilterCriterion`, scoped by a stable `id`.
 *
 * The `id` is the unit of session persistence: `FilterService`
 * stores/loads collections under `polysource.filter.{xxh128(id)}`.
 * Hosts pick `id` based on what makes sense for their UI (e.g. the
 * FQCN of a CrudController, the route name, a tenant scope hash).
 *
 * Immutable: `with()` and `without()` return new instances, never
 * mutate this one. Two criterions with the same `property` are
 * treated as a **replacement at the original position** (the updated
 * criterion stays where the previous one was) — supports the common
 * "user updates an active filter" UX without making the chips bar
 * jump around. Hosts that want "latest update goes to the end" can
 * compose `->without($property)->with($criterion)` instead.
 *
 * @implements IteratorAggregate<int, FilterCriterion>
 */
final class FilterCollection implements IteratorAggregate, Countable
{
    /** @var list<FilterCriterion> */
    public array $criteria;

    /**
     * @param string                    $id       Stable scope identifier (host-defined). Must be non-empty (validated at runtime).
     * @param iterable<FilterCriterion> $criteria active criterions, in display order
     */
    public function __construct(
        public readonly string $id,
        iterable $criteria = [],
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('FilterCollection id cannot be empty.');
        }

        $list = [];
        foreach ($criteria as $criterion) {
            if (!$criterion instanceof FilterCriterion) {
                throw new InvalidArgumentException(\sprintf('FilterCollection criteria must be FilterCriterion instances, got %s.', get_debug_type($criterion)));
            }
            $list[] = $criterion;
        }

        $this->criteria = $list;
    }

    /**
     * Returns a new collection with the given criterion added — or
     * replacing the existing one if a criterion with the same property
     * already exists.
     */
    public function with(FilterCriterion $criterion): self
    {
        $next = [];
        $replaced = false;
        foreach ($this->criteria as $existing) {
            if ($existing->property === $criterion->property) {
                $next[] = $criterion;
                $replaced = true;
                continue;
            }
            $next[] = $existing;
        }
        if (!$replaced) {
            $next[] = $criterion;
        }

        return new self($this->id, $next);
    }

    /**
     * Returns a new collection without the criterion targeting the
     * given property. No-op if no such criterion exists.
     */
    public function without(string $property): self
    {
        $next = array_values(array_filter(
            $this->criteria,
            static fn (FilterCriterion $c): bool => $c->property !== $property,
        ));

        return new self($this->id, $next);
    }

    public function get(string $property): ?FilterCriterion
    {
        foreach ($this->criteria as $criterion) {
            if ($criterion->property === $property) {
                return $criterion;
            }
        }

        return null;
    }

    public function has(string $property): bool
    {
        return null !== $this->get($property);
    }

    public function isEmpty(): bool
    {
        return [] === $this->criteria;
    }

    public function count(): int
    {
        return \count($this->criteria);
    }

    /**
     * @return ArrayIterator<int, FilterCriterion>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->criteria);
    }
}
