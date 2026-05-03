<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ArrayFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ComparisonFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\Demo\EasyAdminBridge\Entity\Product;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * The killer demo of polysource/easyadmin-filter-bridge.
 *
 * Every filter declared below is a stock EasyAdmin filter — the host app
 * code is identical to a non-bridge install. Once the bridge is loaded,
 * each filter automatically gains the enhancement options shipped by the
 * bridge (presets, quick_ranges, include_null, etc.) — see the
 * `setFormTypeOption()` calls below for the per-filter opt-ins.
 */
final class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    /**
     * @return iterable<\EasyCorp\Bundle\EasyAdminBundle\Field\FieldInterface>
     */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name');
        yield TextField::new('description')->onlyOnDetail();
        yield MoneyField::new('price')->setCurrency('EUR');
        yield IntegerField::new('stock');
        yield BooleanField::new('isActive');
        yield ChoiceField::new('status')->setChoices([
            'Draft' => Product::STATUS_DRAFT,
            'Published' => Product::STATUS_PUBLISHED,
            'Archived' => Product::STATUS_ARCHIVED,
        ]);
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('archivedAt')->hideOnIndex();
        yield AssociationField::new('category');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // TextFilter on `name` — raise min_length to 2 so a single
            // character doesn't trigger a wildcard match.
            ->add(
                TextFilter::new('name')
                    ->setFormTypeOption('min_length', 2),
            )
            // NumericFilter on `price` — preset ranges to skip typing.
            ->add(
                NumericFilter::new('price')
                    ->setFormTypeOption('step', 0.01)
                    // Quick-range buckets calibrated on the seeded
                    // product price distribution (min 8€, max 478€,
                    // avg 220€) — each bucket holds ~25% of the rows
                    // so the demo exercises every comparison branch.
                    ->setFormTypeOption('quick_ranges', [
                        ['label' => '< 50€', 'min' => null, 'max' => 50],
                        ['label' => '50–200€', 'min' => 50, 'max' => 200],
                        ['label' => '200–400€', 'min' => 200, 'max' => 400],
                        ['label' => '> 400€', 'min' => 400, 'max' => null],
                    ]),
            )
            // ComparisonFilter on `stock` — only expose >=, <=, =.
            // `value_type` is required by upstream ComparisonFilterType
            // when used directly (vs NumericFilter which presets it).
            ->add(
                ComparisonFilter::new('stock')
                    ->setFormTypeOption('value_type', NumberType::class)
                    ->setFormTypeOption('comparisons', ['=', '>=', '<=']),
            )
            // BooleanFilter on `isActive` — include "Null" choice for
            // when the column is left unset (rare on this demo, but
            // proves the option works).
            ->add(
                BooleanFilter::new('isActive')
                    ->setFormType(EnhancedBooleanFilterType::class)
                    ->setFormTypeOption('include_null', true),
            )
            // DateTimeFilter on `createdAt` — full preset bar.
            ->add(
                DateTimeFilter::new('createdAt')
                    ->setFormTypeOption('show_clear', true),
            )
            // DateTimeFilter on `archivedAt` — same enhancement.
            ->add(
                DateTimeFilter::new('archivedAt')
                    ->setFormTypeOption('show_clear', true),
            )
            // ChoiceFilter on `status` — render inline (3 choices, no
            // need for a dropdown).
            ->add(
                ChoiceFilter::new('status')
                    ->setChoices([
                        'Draft' => Product::STATUS_DRAFT,
                        'Published' => Product::STATUS_PUBLISHED,
                        'Archived' => Product::STATUS_ARCHIVED,
                    ])
                    ->setFormTypeOption('inline', true),
            )
            // ArrayFilter on `tags` — chip display.
            ->add(
                ArrayFilter::new('tags')
                    ->setFormTypeOption('chip_display', true),
            )
            // EntityFilter on `category` — custom placeholder.
            ->add(
                EntityFilter::new('category')
                    ->setFormTypeOption('placeholder', 'Pick a category…'),
            )
        ;
    }
}
