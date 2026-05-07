<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
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
    private const STATUS_CHOICES = [
        'Active' => Product::STATUS_ACTIVE,
        'Draft' => Product::STATUS_DRAFT,
        'Archived' => Product::STATUS_ARCHIVED,
    ];

    private const CATEGORY_CHOICES = [
        'Apparel' => 'apparel',
        'Home & Garden' => 'home-garden',
        'Electronics' => 'electronics',
        'Beauty' => 'beauty',
        'Sports' => 'sports',
        'Books' => 'books',
        'Kitchen' => 'kitchen',
        'Kids' => 'kids',
    ];

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
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->setIcon('fa fa-pen'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a) => $a->setIcon('fa fa-trash'))
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('sku', 'SKU');
            yield TextField::new('name');
            yield MoneyField::new('priceCents', 'Price')->setCurrency('EUR')->setStoredAsCents();
            yield IntegerField::new('stock');
            yield ChoiceField::new('status')->setChoices(self::STATUS_CHOICES)->renderAsBadges([
                Product::STATUS_ACTIVE => 'success',
                Product::STATUS_DRAFT => 'secondary',
                Product::STATUS_ARCHIVED => 'dark',
            ]);
            yield ChoiceField::new('category')->setChoices(self::CATEGORY_CHOICES);
            yield DateTimeField::new('createdAt');

            return;
        }

        yield FormField::addFieldset('Identification')->setIcon('fa fa-tag');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('sku', 'SKU');
        yield TextField::new('name');
        yield SlugField::new('slug')->setTargetFieldName('name');

        yield FormField::addFieldset('Description')->setIcon('fa fa-pen-to-square');
        yield TextareaField::new('description')->setNumOfRows(8);

        yield FormField::addFieldset('Pricing & stock')->setIcon('fa fa-tags');
        yield MoneyField::new('priceCents', 'Price')->setCurrency('EUR')->setStoredAsCents();
        yield IntegerField::new('stock');

        yield FormField::addFieldset('Classification')->setIcon('fa fa-layer-group');
        yield ChoiceField::new('status')->setChoices(self::STATUS_CHOICES);
        yield ChoiceField::new('category')->setChoices(self::CATEGORY_CHOICES);

        yield FormField::addFieldset('Lifecycle')->setIcon('fa fa-clock-rotate-left')->collapsible();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name'))
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUS_CHOICES)->canSelectMultiple())
            ->add(ChoiceFilter::new('category')->setChoices(self::CATEGORY_CHOICES)->canSelectMultiple())
            ->add(NumericFilter::new('priceCents', 'Price (cents)'))
            ->add(NumericFilter::new('stock'))
            ->add(DateTimeFilter::new('createdAt'))
            ->add(FullTextSearchFilter::new('description', 'Full-text in description'))
            ->add(InFilter::new('sku', 'SKU is one of'));
    }
}
