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
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;
use Polysource\EasyAdminFilterBridge\Filter\InFilter;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * Modal-mode demo of polysource/easyadmin-filter-bridge.
 *
 * This CRUD uses EasyAdmin's default centered-modal filter UI
 * with the SAME tabs+groups organisation as
 * {@see CategoryCrudController} — the user can compare both
 * rendering modes (modal vs subpanel) on the same content.
 *
 * Every filter declaration uses marker mode
 * (`Polysource::tab()` / `Polysource::group()`) for ergonomic
 * sequential reading. Per-filter bridge enhancements
 * (presets, quick_ranges, include_null, …) are layered on top.
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
            // ─── Tab "Search" — flat (no groups) ───
            // (Strict-mode rule mirrored from EA's FormField::addTab():
            // once any tab marker is declared, EVERY filter must be
            // under a tab. FilterMarkerProcessor enforces this and
            // throws a LogicException naming the orphans.)
            ->add(Polysource::tab('Search'))
            ->add(
                FullTextSearchFilter::new('q', 'Search anywhere')
                    ->setFormTypeOption('properties', ['name', 'description']),
            )
            ->add(
                TextFilter::new('name')
                    ->setFormTypeOption('min_length', 2),
            )

            // ─── Tab "Pricing" with 2 groups inside ───
            ->add(Polysource::tab('Pricing'))
            ->add(Polysource::group('Price'))
            ->add(
                NumericFilter::new('price')
                    ->setFormTypeOption('step', 0.01)
                    ->setFormTypeOption('quick_ranges', [
                        ['label' => '< 50€', 'min' => null, 'max' => 50],
                        ['label' => '50–200€', 'min' => 50, 'max' => 200],
                        ['label' => '200–400€', 'min' => 200, 'max' => 400],
                        ['label' => '> 400€', 'min' => 400, 'max' => null],
                    ]),
            )
            ->add(Polysource::group('Stock'))
            ->add(
                ComparisonFilter::new('stock')
                    ->setFormTypeOption('value_type', NumberType::class)
                    ->setFormTypeOption('comparisons', ['=', '>=', '<=']),
            )

            // ─── Tab "Lifecycle" with 2 groups inside ───
            ->add(Polysource::tab('Lifecycle'))
            ->add(Polysource::group('Status'))
            ->add(
                BooleanFilter::new('isActive')
                    ->setFormType(EnhancedBooleanFilterType::class)
                    ->setFormTypeOption('include_null', true),
            )
            ->add(
                InFilter::new('status', 'Status (multi)')
                    ->setFormTypeOption('choices', [
                        'Draft' => Product::STATUS_DRAFT,
                        'Published' => Product::STATUS_PUBLISHED,
                        'Archived' => Product::STATUS_ARCHIVED,
                    ]),
            )
            ->add(NotNullFilter::new('description', 'Description state'))
            ->add(Polysource::group('Dates'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(BetweenDateFilter::new('archivedAt', 'Archived between'))

            // ─── Tab "Catalog" without groups (filters render flat in the tab) ───
            ->add(Polysource::tab('Catalog'))
            ->add(
                EntityFilter::new('category')
                    ->setFormTypeOption('placeholder', 'Pick a category…'),
            )
            ->add(
                ArrayFilter::new('tags')
                    ->setFormTypeOption('chip_display', true),
            )
        ;
    }
}
