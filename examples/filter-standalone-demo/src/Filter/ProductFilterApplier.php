<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Filter;

use DateTimeImmutable;
use Polysource\Demo\FilterStandalone\Entity\Product;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;

/**
 * Applies a `FilterCollection` to a list of products in memory.
 *
 * In a real app, the equivalent would be a Doctrine query builder
 * (or a Redis pipeline, an HTTP call, …). The point is that the
 * primitive doesn't dictate where the data lives — the host's
 * applier translates each `FilterCriterion` into whatever query
 * shape its data source needs. Here, plain PHP filters.
 */
final class ProductFilterApplier
{
    /**
     * @param list<Product> $products
     *
     * @return list<Product>
     */
    public function apply(array $products, FilterCollection $collection): array
    {
        foreach ($collection as $criterion) {
            $products = array_values(array_filter(
                $products,
                fn (Product $p): bool => $this->matches($p, $criterion),
            ));
        }

        return $products;
    }

    private function matches(Product $product, FilterCriterion $criterion): bool
    {
        return match ($criterion->property) {
            'category' => \in_array($product->category, $criterion->values, true),
            'price' => $this->matchesPriceRange($product->price, $criterion->values),
            'releasedAt' => $this->matchesReleasedAfter($product->releasedAt, $criterion->values),
            'isAvailable' => $this->matchesAvailability($product->isAvailable, $criterion->values),
            default => true,
        };
    }

    /** @param list<scalar> $values [min, max] */
    private function matchesPriceRange(float $price, array $values): bool
    {
        $min = isset($values[0]) && '' !== $values[0] ? (float) $values[0] : null;
        $max = isset($values[1]) && '' !== $values[1] ? (float) $values[1] : null;

        if (null !== $min && $price < $min) {
            return false;
        }
        if (null !== $max && $price > $max) {
            return false;
        }

        return true;
    }

    /** @param list<scalar> $values [Y-m-d] */
    private function matchesReleasedAfter(DateTimeImmutable $releasedAt, array $values): bool
    {
        if (!isset($values[0]) || '' === $values[0]) {
            return true;
        }

        $threshold = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $values[0]);
        if (false === $threshold) {
            return true;
        }

        return $releasedAt >= $threshold;
    }

    /** @param list<scalar> $values ['0' | '1'] */
    private function matchesAvailability(bool $isAvailable, array $values): bool
    {
        if (!isset($values[0])) {
            return true;
        }

        return $isAvailable === ('1' === (string) $values[0]);
    }
}
