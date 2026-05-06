<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;
use Polysource\EasyAdminFilterBridge\Filter\InFilter;

final class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Product')
            ->setEntityLabelInPlural('Products')
            ->setSearchFields(['name', 'sku', 'slug', 'category'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('sku', 'SKU');
        yield TextField::new('name');
        yield SlugField::new('slug')->setTargetFieldName('name')->hideOnIndex();
        yield TextareaField::new('description')->hideOnIndex();
        yield MoneyField::new('priceCents', 'Price')
            ->setCurrency('EUR')
            ->setStoredAsCents();
        yield IntegerField::new('stock');
        yield ChoiceField::new('status')->setChoices([
            'Active' => Product::STATUS_ACTIVE,
            'Draft' => Product::STATUS_DRAFT,
            'Archived' => Product::STATUS_ARCHIVED,
        ]);
        yield ChoiceField::new('category')->setChoices(array_combine(
            ['Apparel', 'Home & Garden', 'Electronics', 'Beauty', 'Sports', 'Books', 'Kitchen', 'Kids'],
            ['apparel', 'home-garden', 'electronics', 'beauty', 'sports', 'books', 'kitchen', 'kids'],
        ));
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm()->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            // Standard EA filters get auto-enhanced by the
            // polysource/easyadmin-filter-bridge: ChoiceFilter becomes
            // a Select2-style multi-select, DateTimeFilter gains presets,
            // NumericFilter gains a between mode.
            ->add(TextFilter::new('name'))
            ->add(ChoiceFilter::new('status')->setChoices([
                'Active' => Product::STATUS_ACTIVE,
                'Draft' => Product::STATUS_DRAFT,
                'Archived' => Product::STATUS_ARCHIVED,
            ])->canSelectMultiple())
            ->add(ChoiceFilter::new('category')->setChoices(array_combine(
                ['Apparel', 'Home & Garden', 'Electronics', 'Beauty', 'Sports', 'Books', 'Kitchen', 'Kids'],
                ['apparel', 'home-garden', 'electronics', 'beauty', 'sports', 'books', 'kitchen', 'kids'],
            ))->canSelectMultiple())
            ->add(NumericFilter::new('priceCents', 'Price (cents)'))
            ->add(NumericFilter::new('stock'))
            ->add(DateTimeFilter::new('createdAt'))
            // Custom filters from the bridge — direct opt-in.
            ->add(FullTextSearchFilter::new('description', 'Full-text in description'))
            ->add(InFilter::new('sku', 'SKU is one of'))
            ;
    }
}
