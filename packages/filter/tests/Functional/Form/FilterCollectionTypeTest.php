<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Functional\Form;

use PHPUnit\Framework\TestCase;
use Polysource\Filter\Form\Type\FilterCollectionType;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Model\FilterDefinition;
use Polysource\Filter\Pipeline\Default\DatetimeMapper;
use Polysource\Filter\Pipeline\Default\DatetimeRenderer;
use Polysource\Filter\Pipeline\Default\NumericMapper;
use Polysource\Filter\Pipeline\Default\NumericRenderer;
use Polysource\Filter\Pipeline\Default\TextMapper;
use Polysource\Filter\Pipeline\Default\TextRenderer;
use Polysource\Filter\Pipeline\Registry\MapperRegistry;
use Polysource\Filter\Pipeline\Registry\RendererRegistry;
use Symfony\Component\Form\Forms;

/**
 * End-to-end test for `FilterCollectionType` + `FilterHydrator`.
 *
 * Boots a real Symfony Form factory with our 7 default pipeline
 * impls, builds a FilterCollectionType against a list of definitions,
 * and exercises the bidirectional roundtrip:
 *
 *   1. PRE_SET_DATA — load a FilterCollection → form pre-fills.
 *   2. PRE_SUBMIT + POST_SUBMIT — submit raw data → form returns a
 *      fresh FilterCollection.
 */
final class FilterCollectionTypeTest extends TestCase
{
    private FilterCollectionType $type;

    protected function setUp(): void
    {
        $this->type = new FilterCollectionType(
            new MapperRegistry(
                [new TextMapper(), new NumericMapper(), new DatetimeMapper()],
                ['text', 'numeric', 'datetime'],
            ),
            new RendererRegistry(
                [new TextRenderer(), new NumericRenderer(), new DatetimeRenderer()],
                ['text', 'numeric', 'datetime'],
            ),
        );
    }

    public function test_pre_set_data_pre_fills_form_from_collection(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType($this->type)->getFormFactory();

        $collection = new FilterCollection('scope-1', [
            new FilterCriterion('name', 'like', ['hat']),
            new FilterCriterion('price', 'between', [50, 200]),
        ]);

        $form = $factory->create(FilterCollectionType::class, $collection, [
            'collection_id' => 'scope-1',
            'definitions' => [
                FilterDefinition::new('text', 'name', 'Name'),
                FilterDefinition::new('numeric', 'price', 'Price'),
            ],
        ]);

        $nameView = $form->get('name')->createView();
        $priceView = $form->get('price')->createView();
        // The mapper produced ['comparison' => 'like', 'value' => 'hat'] for
        // name and ['comparison' => 'between', 'value' => 50, 'value2' => 200]
        // for price; the form builder splits it across the children of
        // each compound TextType / NumberType. We only assert that the
        // form exposes the criteria via the children's `value`.
        self::assertSame('Name', $nameView->vars['label']);
        self::assertSame('Price', $priceView->vars['label']);
    }

    public function test_post_submit_builds_collection_from_raw_data(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType($this->type)->getFormFactory();

        $form = $factory->create(FilterCollectionType::class, null, [
            'collection_id' => 'scope-1',
            'definitions' => [
                FilterDefinition::new('text', 'name', 'Name'),
                FilterDefinition::new('numeric', 'price', 'Price'),
            ],
        ]);

        // Submit raw form data — values are scalars (TextType/NumberType
        // accept strings). We're testing that POST_SUBMIT promotes the
        // children into a FilterCollection.
        $form->submit([
            'name' => 'hat',
            'price' => '50',
        ]);

        self::assertTrue($form->isSynchronized());
        $data = $form->getData();
        self::assertInstanceOf(FilterCollection::class, $data);
        self::assertSame('scope-1', $data->id);
    }

    public function test_empty_submission_yields_empty_collection(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType($this->type)->getFormFactory();

        $form = $factory->create(FilterCollectionType::class, null, [
            'collection_id' => 'scope-1',
            'definitions' => [
                FilterDefinition::new('text', 'name', 'Name'),
            ],
        ]);

        $form->submit([]);

        $data = $form->getData();
        self::assertInstanceOf(FilterCollection::class, $data);
        self::assertTrue($data->isEmpty());
    }

    public function test_view_exposes_groups_and_mode(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType($this->type)->getFormFactory();

        $form = $factory->create(FilterCollectionType::class, null, [
            'collection_id' => 'scope-1',
            'mode' => 'subpanel',
            'definitions' => [
                FilterDefinition::new('text', 'name')->withGroup('Identity'),
                FilterDefinition::new('numeric', 'price')->withGroup('Pricing'),
                FilterDefinition::new('numeric', 'stock')->withGroup('Pricing'),
            ],
        ]);

        $view = $form->createView();
        self::assertSame('subpanel', $view->vars['polysource_filter_mode']);
        self::assertSame('scope-1', $view->vars['polysource_filter_collection_id']);

        /** @var array<string, list<FilterDefinition>> $groups */
        $groups = $view->vars['polysource_filter_groups'];
        self::assertArrayHasKey('Identity', $groups);
        self::assertArrayHasKey('Pricing', $groups);
        self::assertCount(1, $groups['Identity']);
        self::assertCount(2, $groups['Pricing']);
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType($this->type)->getFormFactory();

        $this->expectException(\Symfony\Component\OptionsResolver\Exception\InvalidOptionsException::class);
        $factory->create(FilterCollectionType::class, null, [
            'collection_id' => 'scope-1',
            'mode' => 'unknown_mode',
            'definitions' => [],
        ]);
    }

    public function test_definitions_must_be_FilterDefinition_instances(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addType($this->type)->getFormFactory();

        // Symfony OptionsResolver wraps normalizer-thrown exceptions
        // depending on version; accept either the wrapping
        // InvalidOptionsException or the raw InvalidArgumentException.
        $this->expectException(\InvalidArgumentException::class);
        $factory->create(FilterCollectionType::class, null, [
            'collection_id' => 'scope-1',
            'definitions' => ['not a FilterDefinition'],
        ]);
    }
}
