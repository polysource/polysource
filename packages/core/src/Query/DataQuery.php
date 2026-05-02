<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Read query parameters passed to a {@see \Polysource\Core\DataSource\DataSourceInterface}.
 *
 * Immutable. Use the with*() methods to derive a modified instance.
 *
 * @see \Polysource\Core\DataSource\DataSourceInterface::search()
 */
final readonly class DataQuery
{
    /**
     * @param array<string, FilterCriterion> $filters     map of filter name => criterion
     * @param array<string, SortDirection>   $sort        map of property name => direction
     */
    public function __construct(
        public string $resourceName,
        public ?string $searchText = null,
        public array $filters = [],
        public array $sort = [],
        public ?Pagination $pagination = null,
    ) {
    }

    public function withSearchText(?string $searchText): self
    {
        return new self(
            $this->resourceName,
            $searchText,
            $this->filters,
            $this->sort,
            $this->pagination,
        );
    }

    public function withFilter(string $name, FilterCriterion $criterion): self
    {
        $filters = $this->filters;
        $filters[$name] = $criterion;

        return new self(
            $this->resourceName,
            $this->searchText,
            $filters,
            $this->sort,
            $this->pagination,
        );
    }

    public function withoutFilter(string $name): self
    {
        $filters = $this->filters;
        unset($filters[$name]);

        return new self(
            $this->resourceName,
            $this->searchText,
            $filters,
            $this->sort,
            $this->pagination,
        );
    }

    public function withSort(string $property, SortDirection $direction): self
    {
        $sort = $this->sort;
        $sort[$property] = $direction;

        return new self(
            $this->resourceName,
            $this->searchText,
            $this->filters,
            $sort,
            $this->pagination,
        );
    }

    public function withPagination(?Pagination $pagination): self
    {
        return new self(
            $this->resourceName,
            $this->searchText,
            $this->filters,
            $this->sort,
            $pagination,
        );
    }
}
