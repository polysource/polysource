<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Twig;

use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Bridge\BridgeOptions;
use Polysource\EasyAdminFilterBridge\Twig\FilterTreeBuilder;

/**
 * Behavioural tests for {@see FilterTreeBuilder}.
 *
 * Verifies the JSON tree shape consumed by the
 * `polysource--filter-modal-layout` Stimulus controller.
 */
final class FilterTreeBuilderTest extends TestCase
{
    public function testNullConfigReturnsEmptyTree(): void
    {
        $tree = (new FilterTreeBuilder())->build(null);

        self::assertSame(['ungrouped' => [], 'groups' => [], 'tabs' => []], $tree);
    }

    public function testFiltersWithoutCustomOptionsLandInUngrouped(): void
    {
        $config = new FilterConfigDto();
        $config->addFilter(TextFilter::new('name'));
        $config->addFilter(TextFilter::new('q'));

        $tree = (new FilterTreeBuilder())->build($config);

        self::assertSame(['name', 'q'], $tree['ungrouped']);
        self::assertEmpty($tree['groups']);
        self::assertEmpty($tree['tabs']);
    }

    public function testFiltersWithGroupOnlyLandInTopLevelGroups(): void
    {
        $a = TextFilter::new('isActive');
        $a->getAsDto()->setCustomOption(BridgeOptions::GROUP, 'Status');
        $b = TextFilter::new('isPublished');
        $b->getAsDto()->setCustomOption(BridgeOptions::GROUP, 'Status');

        $config = new FilterConfigDto();
        $config->addFilter($a);
        $config->addFilter($b);

        $tree = (new FilterTreeBuilder())->build($config);

        self::assertEmpty($tree['ungrouped']);
        self::assertCount(1, $tree['groups']);
        self::assertSame('Status', $tree['groups'][0]['label']);
        self::assertSame(['isActive', 'isPublished'], $tree['groups'][0]['properties']);
    }

    public function testFiltersWithTabAndGroupNest(): void
    {
        $a = TextFilter::new('isVisible');
        $a->getAsDto()->setCustomOption(BridgeOptions::TAB, 'Visibility');
        $a->getAsDto()->setCustomOption(BridgeOptions::GROUP, 'Active state');

        $b = TextFilter::new('createdAt');
        $b->getAsDto()->setCustomOption(BridgeOptions::TAB, 'Dates');

        $config = new FilterConfigDto();
        $config->addFilter($a);
        $config->addFilter($b);

        $tree = (new FilterTreeBuilder())->build($config);

        self::assertCount(2, $tree['tabs']);
        self::assertSame('Visibility', $tree['tabs'][0]['label']);
        self::assertCount(1, $tree['tabs'][0]['groups']);
        self::assertSame('Active state', $tree['tabs'][0]['groups'][0]['label']);
        self::assertSame(['isVisible'], $tree['tabs'][0]['groups'][0]['properties']);

        self::assertSame('Dates', $tree['tabs'][1]['label']);
        self::assertSame(['createdAt'], $tree['tabs'][1]['ungrouped']);
        self::assertEmpty($tree['tabs'][1]['groups']);
    }

    public function testMixedTreeShape(): void
    {
        // 2 ungrouped + 1 group-only + 2 tabs (one with group + ungrouped, one with 2 groups)
        $configFilters = [
            ['name', null, null],
            ['q', null, null],
            ['isActive', null, 'Status'],
            ['isPublished', null, 'Status'],
            ['isVisible', 'Visibility', 'Active state'],
            ['flagInTab', 'Visibility', null],
            ['createdAt', 'Dates', 'Lifecycle'],
            ['archivedAt', 'Dates', 'Other'],
        ];

        $config = new FilterConfigDto();
        foreach ($configFilters as [$prop, $tab, $group]) {
            $f = TextFilter::new($prop);
            if (null !== $tab) {
                $f->getAsDto()->setCustomOption(BridgeOptions::TAB, $tab);
            }
            if (null !== $group) {
                $f->getAsDto()->setCustomOption(BridgeOptions::GROUP, $group);
            }
            $config->addFilter($f);
        }

        $tree = (new FilterTreeBuilder())->build($config);

        self::assertSame(['name', 'q'], $tree['ungrouped']);
        self::assertCount(1, $tree['groups']);
        self::assertSame('Status', $tree['groups'][0]['label']);
        self::assertSame(['isActive', 'isPublished'], $tree['groups'][0]['properties']);

        self::assertCount(2, $tree['tabs']);
        self::assertSame('Visibility', $tree['tabs'][0]['label']);
        self::assertSame(['flagInTab'], $tree['tabs'][0]['ungrouped']);
        self::assertCount(1, $tree['tabs'][0]['groups']);
        self::assertSame('Active state', $tree['tabs'][0]['groups'][0]['label']);
        self::assertSame(['isVisible'], $tree['tabs'][0]['groups'][0]['properties']);

        self::assertSame('Dates', $tree['tabs'][1]['label']);
        self::assertCount(2, $tree['tabs'][1]['groups']);
        self::assertSame('Lifecycle', $tree['tabs'][1]['groups'][0]['label']);
        self::assertSame('Other', $tree['tabs'][1]['groups'][1]['label']);
    }

    public function testEmptyStringTabOrGroupTreatedAsAbsent(): void
    {
        $f = TextFilter::new('name');
        $f->getAsDto()->setCustomOption(BridgeOptions::TAB, '');
        $f->getAsDto()->setCustomOption(BridgeOptions::GROUP, '');

        $config = new FilterConfigDto();
        $config->addFilter($f);

        $tree = (new FilterTreeBuilder())->build($config);

        self::assertSame(['name'], $tree['ungrouped']);
        self::assertEmpty($tree['groups']);
        self::assertEmpty($tree['tabs']);
    }
}
