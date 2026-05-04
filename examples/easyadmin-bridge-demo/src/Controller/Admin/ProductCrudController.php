<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
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
use EasyCorp\Bundle\EasyAdminBundle\Filter\ComparisonFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\Demo\EasyAdminBridge\Entity\Product;
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;
use Polysource\EasyAdminFilterBridge\Filter\InFilter;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * Modal-mode demo of polysource/easyadmin-filter-bridge.
 *
 * This CRUD uses EasyAdmin's default centered-modal filter UI.
 * Every filter is a stock EasyAdmin filter — the host app code is
 * identical to a non-bridge install. Once the bridge is loaded,
 * each filter automatically gains the bridge-side enhancements
 * (presets, quick_ranges, include_null, …) plus the chips bar
 * above the table.
 *
 * For the subpanel-mode + multi-group demo, see
 * {@see CategoryCrudController}.
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
        yield ArrayField::new('tags')->hideOnIndex();
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
            // ─── 4 custom filter types shipped by the bridge ───
            // (Phase 9.7 livrables §3 — usable manually via
            // configureFilters(); not auto-applied like the 8
            // enhancers above.)

            // BetweenDateFilter on `archivedAt` — strips EA's
            // comparison dropdown, always emits BETWEEN with
            // graceful one-sided fallback (only-from → `>=`,
            // only-to → `<=`). Replaces a second DateTimeFilter
            // that would otherwise force users through the
            // "Between" comparison toggle.
            ->add(
                BetweenDateFilter::new('archivedAt', 'Archived between'),
            )
            // InFilter on `status` — multi-select status picker
            // emitting `IN (…)`. Replaces the upstream `ChoiceFilter`
            // (single-value) so users can pick e.g. "Draft +
            // Published" in one go.
            ->add(
                InFilter::new('status', 'Status (multi)')
                    ->setFormTypeOption('choices', [
                        'Draft' => Product::STATUS_DRAFT,
                        'Published' => Product::STATUS_PUBLISHED,
                        'Archived' => Product::STATUS_ARCHIVED,
                    ]),
            )
            // NotNullFilter on `description` — tri-state toggle
            // (Any / Has value / Empty). Demonstrates the
            // "filter rows where this nullable column is
            // populated" UX which EA built-ins cannot express.
            ->add(
                NotNullFilter::new('description', 'Description state'),
            )
            // FullTextSearchFilter on synthetic `q` — single
            // text input matched LIKE-OR'd across `name` and
            // `description`. Demonstrates a cheap multi-column
            // search without standing up Meilisearch.
            ->add(
                FullTextSearchFilter::new('q', 'Search anywhere')
                    ->setFormTypeOption('properties', ['name', 'description']),
            )
        ;
    }
}
