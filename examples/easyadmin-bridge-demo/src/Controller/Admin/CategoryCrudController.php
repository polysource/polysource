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
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\EnhancedBooleanFilterType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

/**
 * Subpanel-mode + multi-group demo of polysource/easyadmin-filter-bridge.
 *
 * Wired to render the filter UI as a right-anchored slide-in panel
 * instead of EasyAdmin's centered modal, with filters bucketed
 * into 3 logical groups via `setFormTypeOption('polysource_group',
 * '…')`. The page is otherwise an ordinary EA CRUD on the
 * `Category` entity.
 *
 * Pair this with {@see ProductCrudController} (modal mode, no
 * groups) to compare the two layouts side-by-side from the menu.
 */
final class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        // Opt into subpanel mode: filters slide in from the right
        // edge instead of opening as a centered modal. Driven by
        // the `polysource-filter-subpanel` body class + bridge CSS.
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
        yield BooleanField::new('isVisible');
        yield IntegerField::new('displayOrder');
        yield DateTimeField::new('createdAt');
        yield DateTimeField::new('archivedAt')->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // ─── Ungrouped filters (rendered flat at the top) ───
            ->add(TextFilter::new('name'))
            ->add(
                FullTextSearchFilter::new('q', 'Search anywhere')
                    ->setFormTypeOption('properties', ['name', 'slug', 'description']),
            )

            // ─── "Visibility" group ───
            ->add(
                BooleanFilter::new('isVisible')
                    ->setFormType(EnhancedBooleanFilterType::class)
                    ->setFormTypeOption('include_null', false)
                    ->setFormTypeOption('polysource_group', 'Visibility'),
            )
            ->add(
                NotNullFilter::new('description', 'Description state')
                    ->setFormTypeOption('polysource_group', 'Visibility'),
            )

            // ─── "Display" group ───
            ->add(
                ComparisonFilter::new('displayOrder')
                    ->setFormTypeOption('value_type', NumberType::class)
                    ->setFormTypeOption('comparisons', ['=', '>=', '<='])
                    ->setFormTypeOption('polysource_group', 'Display'),
            )
            ->add(
                TextFilter::new('slug')
                    ->setFormTypeOption('polysource_group', 'Display'),
            )

            // ─── "Dates" group ───
            ->add(
                DateTimeFilter::new('createdAt')
                    ->setFormTypeOption('polysource_group', 'Dates'),
            )
            ->add(
                BetweenDateFilter::new('archivedAt', 'Archived between')
                    ->setFormTypeOption('polysource_group', 'Dates'),
            )
        ;
    }
}
