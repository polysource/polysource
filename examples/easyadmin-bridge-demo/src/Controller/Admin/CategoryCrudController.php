<?php

declare(strict_types=1);

namespace Polysource\Demo\EasyAdminBridge\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ComparisonFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\Demo\EasyAdminBridge\Entity\Category;
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * Subpanel-mode + multi-tab + multi-group demo of
 * polysource/easyadmin-filter-bridge.
 *
 * Demonstrates the FULL Polysource filter organisation API:
 *
 * 1. **Subpanel mode**: filter UI slides in from the right edge
 *    via `overrideTemplate('crud/index', '@PolysourceEasyAdminFilterBridge/crud/index_subpanel.html.twig')`.
 *
 * 2. **Marker mode declaration** (à la EA's `FormField::addTab()`):
 *    `Polysource::tab()` / `Polysource::group()` markers yielded
 *    between filter declarations propagate to subsequent filters.
 *    Per-filter explicit declarations
 *    (`Polysource::filter($f)->tab(...)`) always override.
 *
 * 3. **2-level hierarchy**: filters bucketed into 3 tabs ("Visibility",
 *    "Dates", "Display"), each with optional groups within
 *    (e.g. "Visibility" → "Active state" + "Description state").
 *    The Stimulus `polysource--filter-modal-layout` controller
 *    renders this as Bootstrap nav-tabs + nested `<details>`
 *    accordions per group.
 *
 * 4. **Field ↔ chip coherence**: the `isVisible` field declares a
 *    chipFormatter via `Polysource::field()->chipFormatter()`.
 *    BOTH the table column AND the active-filters chip use the
 *    same callable — solves the "what if the host has custom
 *    field rendering?" coherence concern.
 *
 * Pair this with {@see ProductCrudController} (modal mode, no
 * tabs/groups) to compare the two layouts side-by-side from the
 * dashboard menu.
 */
final class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->overrideTemplate(
            'crud/index',
            '@PolysourceEasyAdminFilterBridge/crud/index_subpanel.html.twig',
        );
    }

    /**
     * @return iterable<\EasyCorp\Bundle\EasyAdminBundle\Field\FieldInterface>
     */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name');
        yield TextField::new('slug');
        yield TextField::new('description')->onlyOnDetail();

        // Polysource::field() proxies the BooleanField and writes
        // the chipFormatter callable on the FieldDto's
        // customOptions. ChipValueFormatter (5-stage chain, stage 2)
        // looks it up by property name and uses it in lieu of the
        // default Yes/No translation. Both the table column AND
        // the chip render with this callable.
        yield Polysource::field(BooleanField::new('isVisible'))
            ->chipFormatter(static fn (mixed $v): string => true === $v || '1' === $v || 1 === $v ? '👁️ Visible' : '🚫 Caché');

        yield IntegerField::new('displayOrder');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('archivedAt')->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // ─── Tab "Search" — flat (no groups) ───
            // Strict-mode (mirrors EA's FormField::addTab()): once any
            // tab marker is declared, EVERY filter must be under a tab.
            ->add(Polysource::tab('Search'))
            ->add(TextFilter::new('name'))
            ->add(
                FullTextSearchFilter::new('q', 'Search anywhere')
                    ->setFormTypeOption('properties', ['name', 'slug', 'description']),
            )

            // ─── Tab "Visibility" with 2 groups inside ───
            ->add(Polysource::tab('Visibility'))
            ->add(Polysource::group('Active state'))
            ->add(
                BooleanFilter::new('isVisible')
                    ->setFormType(EnhancedBooleanFilterType::class)
                    ->setFormTypeOption('include_null', false),
            )
            ->add(Polysource::group('Description state'))
            ->add(NotNullFilter::new('description', 'Description state'))

            // ─── Tab "Dates" with 2 groups inside (tab change resets group) ───
            ->add(Polysource::tab('Dates'))
            ->add(Polysource::group('Lifecycle'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(Polysource::group('Archive'))
            ->add(BetweenDateFilter::new('archivedAt', 'Archived between'))

            // ─── Tab "Display" without groups (filters render flat in tab) ───
            ->add(Polysource::tab('Display'))
            ->add(TextFilter::new('slug'))
            ->add(
                ComparisonFilter::new('displayOrder')
                    ->setFormTypeOption('value_type', NumberType::class)
                    ->setFormTypeOption('comparisons', ['=', '>=', '<=']),
            )
        ;
    }
}
