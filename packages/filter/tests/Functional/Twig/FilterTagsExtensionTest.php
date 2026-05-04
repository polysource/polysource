<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Functional\Twig;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Model\FilterDefinition;
use Polysource\Filter\Pipeline\Default\NumericFormatter;
use Polysource\Filter\Pipeline\Default\TextFormatter;
use Polysource\Filter\Pipeline\Registry\FormatterRegistry;
use Polysource\Filter\Twig\Extension\FilterTagsExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Tests `filter_tags(collection, definitions)` against the actual
 * chips template — verifies chips render, the X button is wired with
 * the right Stimulus action+param, and the overflow ("+N more")
 * appears past 7 chips.
 */
final class FilterTagsExtensionTest extends TestCase
{
    private Environment $twig;
    private FilterTagsExtension $extension;

    protected function setUp(): void
    {
        $bridgeRoot = \dirname(__DIR__, 3);
        $loader = new FilesystemLoader();
        $loader->addPath($bridgeRoot . '/Resources/views', 'PolysourceFilter');

        $this->twig = new Environment($loader, ['strict_variables' => true]);
        $translator = new Translator('en');
        $this->twig->addExtension(new TranslationExtension($translator));

        $registry = new FormatterRegistry(
            [new TextFormatter(), new NumericFormatter()],
            ['text', 'numeric'],
        );

        $this->extension = new FilterTagsExtension($registry, $this->twig);
        $this->twig->addExtension($this->extension);
    }

    public function testRendersOneChipPerActiveCriterion(): void
    {
        $collection = new FilterCollection('scope-1', [
            new FilterCriterion('name', 'like', ['hat']),
            new FilterCriterion('price', 'between', [50, 200]),
        ]);
        $definitions = [
            FilterDefinition::new('text', 'name', 'Name'),
            FilterDefinition::new('numeric', 'price', 'Price'),
        ];

        $html = $this->extension->renderTags($collection, $definitions);

        self::assertStringContainsString('class="polysource-filter-chips', $html);
        self::assertStringContainsString('data-controller="polysource--filter-chips"', $html);
        self::assertStringContainsString('data-property="name"', $html);
        self::assertStringContainsString('data-property="price"', $html);
        self::assertStringContainsString('data-polysource--filter-chips-property-param="name"', $html);
        self::assertStringContainsString('data-polysource--filter-chips-property-param="price"', $html);
        self::assertSame(2, substr_count($html, 'polysource-filter-chip-remove'));
    }

    public function testSkipsChipsForUnknownFilterNames(): void
    {
        $collection = new FilterCollection('scope-1', [
            new FilterCriterion('name', 'like', ['hat']),
            new FilterCriterion('mystery', '=', ['x']),
        ]);
        $definitions = [
            FilterDefinition::new('text', 'name', 'Name'),
            FilterDefinition::new('unknown_filter_name', 'mystery'),
        ];

        $html = $this->extension->renderTags($collection, $definitions);

        // `name` chip rendered, `mystery` skipped (no formatter for
        // 'unknown_filter_name').
        self::assertStringContainsString('data-property="name"', $html);
        self::assertStringNotContainsString('data-property="mystery"', $html);
    }

    public function testRendersOverflowTogglePast7Chips(): void
    {
        $criteria = [];
        $definitions = [];
        for ($i = 1; $i <= 9; ++$i) {
            $criteria[] = new FilterCriterion("p{$i}", '=', ["v{$i}"]);
            $definitions[] = FilterDefinition::new('text', "p{$i}");
        }
        $collection = new FilterCollection('scope-1', $criteria);

        $html = $this->extension->renderTags($collection, $definitions);

        self::assertStringContainsString('polysource-filter-chips-overflow-toggle', $html);
        self::assertStringContainsString('polysource--filter-chips#expandOverflow', $html);
        // 9 chips total: 7 visible + 2 in the hidden overflow region.
        // Each chip includes a property data-attr so we can count.
        $visibleCount = preg_match_all('/data-polysource--filter-chips-target="chip"/', $html);
        self::assertSame(9, $visibleCount, 'all 9 chips rendered (7 visible + 2 in overflow)');
    }

    public function testNoOverflowWhenAtOrBelow7Chips(): void
    {
        $criteria = [];
        $definitions = [];
        for ($i = 1; $i <= 7; ++$i) {
            $criteria[] = new FilterCriterion("p{$i}", '=', ["v{$i}"]);
            $definitions[] = FilterDefinition::new('text', "p{$i}");
        }
        $collection = new FilterCollection('scope-1', $criteria);

        $html = $this->extension->renderTags($collection, $definitions);

        self::assertStringNotContainsString('overflow-toggle', $html);
    }

    public function testEmptyCollectionRendersEmptyChipsBar(): void
    {
        $collection = new FilterCollection('scope-1');

        $html = $this->extension->renderTags($collection, []);

        self::assertStringContainsString('polysource-filter-chips', $html);
        self::assertStringNotContainsString('polysource-filter-chip-remove', $html);
    }
}
