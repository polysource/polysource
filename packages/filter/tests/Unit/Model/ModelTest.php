<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Model\FilterDefinition;

/**
 * Unit tests for the 3 domain value objects.
 *
 * Focuses on:
 * - immutability (with*() returns a new instance)
 * - validation invariants (empty strings rejected, list semantics)
 * - replacement semantics in FilterCollection::with()
 */
final class ModelTest extends TestCase
{
    // -------------------------------------------------------------------
    // FilterCriterion
    // -------------------------------------------------------------------

    public function test_criterion_stores_property_operator_values(): void
    {
        $c = new FilterCriterion('createdAt', 'between', ['2026-01-01', '2026-12-31']);

        self::assertSame('createdAt', $c->property);
        self::assertSame('between', $c->operator);
        self::assertSame(['2026-01-01', '2026-12-31'], $c->values);
    }

    public function test_criterion_defaults_values_to_empty_list(): void
    {
        $c = new FilterCriterion('isArchived', 'isNull');
        self::assertSame([], $c->values);
    }

    public function test_criterion_rejects_empty_property(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FilterCriterion('', '=', ['x']);
    }

    public function test_criterion_rejects_empty_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FilterCriterion('name', '', ['x']);
    }

    public function test_criterion_rejects_associative_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line argument.type */
        new FilterCriterion('name', '=', ['key' => 'value']);
    }

    public function test_criterion_with_operator_returns_new_instance(): void
    {
        $original = new FilterCriterion('price', '=', [42]);
        $modified = $original->withOperator('>=');

        self::assertNotSame($original, $modified);
        self::assertSame('=', $original->operator, 'original must be unchanged');
        self::assertSame('>=', $modified->operator);
        self::assertSame([42], $modified->values, 'values must be preserved');
    }

    public function test_criterion_with_values_returns_new_instance(): void
    {
        $original = new FilterCriterion('price', 'between', [10, 20]);
        $modified = $original->withValues([50, 100]);

        self::assertSame([10, 20], $original->values);
        self::assertSame([50, 100], $modified->values);
        self::assertSame('between', $modified->operator);
    }

    public function test_criterion_equals_compares_structurally(): void
    {
        $a = new FilterCriterion('p', '=', [1, 2]);
        $b = new FilterCriterion('p', '=', [1, 2]);
        $c = new FilterCriterion('p', '=', [1, 3]);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    // -------------------------------------------------------------------
    // FilterCollection
    // -------------------------------------------------------------------

    public function test_collection_stores_id_and_criteria(): void
    {
        $criteria = [
            new FilterCriterion('p1', '=', ['a']),
            new FilterCriterion('p2', '>', [10]),
        ];
        $coll = new FilterCollection('scope', $criteria);

        self::assertSame('scope', $coll->id);
        self::assertSame($criteria, $coll->criteria);
        self::assertCount(2, $coll);
        self::assertFalse($coll->isEmpty());
    }

    public function test_collection_rejects_empty_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FilterCollection('', []);
    }

    public function test_collection_rejects_non_criterion_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore-next-line argument.type */
        new FilterCollection('scope', ['not a criterion']);
    }

    public function test_collection_with_appends_new_criterion(): void
    {
        $coll = new FilterCollection('s', [new FilterCriterion('p1', '=', ['a'])]);

        $next = $coll->with(new FilterCriterion('p2', '>', [10]));

        self::assertCount(1, $coll);
        self::assertCount(2, $next);
        self::assertSame('p2', $next->criteria[1]->property);
    }

    public function test_collection_with_replaces_same_property_criterion(): void
    {
        $coll = new FilterCollection('s', [
            new FilterCriterion('p1', '=', ['old']),
            new FilterCriterion('p2', '>', [10]),
        ]);

        $next = $coll->with(new FilterCriterion('p1', 'like', ['new']));

        self::assertCount(2, $next);
        self::assertSame('p1', $next->criteria[0]->property);
        self::assertSame('like', $next->criteria[0]->operator);
        self::assertSame(['new'], $next->criteria[0]->values);
        self::assertSame('p2', $next->criteria[1]->property, 'order preserved');
    }

    public function test_collection_without_removes_criterion_by_property(): void
    {
        $coll = new FilterCollection('s', [
            new FilterCriterion('p1', '=', ['a']),
            new FilterCriterion('p2', '>', [10]),
        ]);

        $next = $coll->without('p1');

        self::assertCount(2, $coll, 'original unchanged');
        self::assertCount(1, $next);
        self::assertSame('p2', $next->criteria[0]->property);
    }

    public function test_collection_without_is_noop_when_property_missing(): void
    {
        $coll = new FilterCollection('s', [new FilterCriterion('p1', '=', ['a'])]);
        $next = $coll->without('does_not_exist');

        self::assertCount(1, $next);
    }

    public function test_collection_get_and_has(): void
    {
        $coll = new FilterCollection('s', [new FilterCriterion('p1', '=', ['a'])]);

        self::assertTrue($coll->has('p1'));
        self::assertFalse($coll->has('p2'));
        self::assertNotNull($coll->get('p1'));
        self::assertNull($coll->get('p2'));
    }

    public function test_collection_iterates_in_order(): void
    {
        $criteria = [
            new FilterCriterion('p1', '=', ['a']),
            new FilterCriterion('p2', '>', [10]),
            new FilterCriterion('p3', 'in', [1, 2, 3]),
        ];
        $coll = new FilterCollection('s', $criteria);

        $iterated = [];
        foreach ($coll as $criterion) {
            $iterated[] = $criterion->property;
        }

        self::assertSame(['p1', 'p2', 'p3'], $iterated);
    }

    public function test_collection_is_empty_returns_true_for_no_criteria(): void
    {
        $coll = new FilterCollection('s', []);
        self::assertTrue($coll->isEmpty());
        self::assertCount(0, $coll);
    }

    // -------------------------------------------------------------------
    // FilterDefinition
    // -------------------------------------------------------------------

    public function test_definition_new_factory_with_minimal_args(): void
    {
        $d = FilterDefinition::new('datetime', 'createdAt');

        self::assertSame('datetime', $d->name);
        self::assertSame('createdAt', $d->property);
        self::assertSame('', $d->label);
        self::assertNull($d->group);
        self::assertSame([], $d->formSpec);
        self::assertSame([], $d->datasourceSpec);
    }

    public function test_definition_constructor_with_all_args(): void
    {
        $d = new FilterDefinition(
            name: 'numeric',
            property: 'price',
            label: 'Price (€)',
            group: 'Pricing',
            formSpec: ['quick_ranges' => [['min' => 0, 'max' => 50]]],
            datasourceSpec: ['column' => 'p.price'],
        );

        self::assertSame('Price (€)', $d->label);
        self::assertSame('Pricing', $d->group);
        self::assertSame([['min' => 0, 'max' => 50]], $d->formSpec['quick_ranges']);
        self::assertSame('p.price', $d->datasourceSpec['column']);
    }

    public function test_definition_rejects_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FilterDefinition('', 'p');
    }

    public function test_definition_rejects_empty_property(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FilterDefinition('name', '');
    }

    public function test_definition_rejects_empty_string_group_but_accepts_null(): void
    {
        $ok = FilterDefinition::new('n', 'p')->withGroup(null);
        self::assertNull($ok->group);

        $this->expectException(\InvalidArgumentException::class);
        FilterDefinition::new('n', 'p')->withGroup('');
    }

    public function test_definition_with_setters_return_new_instances(): void
    {
        $base = FilterDefinition::new('text', 'name');

        $labeled = $base->withLabel('Name');
        $grouped = $labeled->withGroup('Identity');
        $formed = $grouped->withFormSpec(['min_length' => 2]);
        $datasourced = $formed->withDatasourceSpec(['columns' => ['p.name']]);

        self::assertNotSame($base, $labeled);
        self::assertNotSame($labeled, $grouped);
        self::assertSame('', $base->label, 'original immutable');
        self::assertSame('Name', $datasourced->label);
        self::assertSame('Identity', $datasourced->group);
        self::assertSame(['min_length' => 2], $datasourced->formSpec);
        self::assertSame(['columns' => ['p.name']], $datasourced->datasourceSpec);
    }
}
