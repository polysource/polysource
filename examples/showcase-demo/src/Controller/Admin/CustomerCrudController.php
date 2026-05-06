<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\EasyAdminFilterBridge\Filter\InFilter;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;

final class CustomerCrudController extends AbstractCrudController
{
    private const COUNTRIES = [
        'France' => 'FR', 'Germany' => 'DE', 'Spain' => 'ES', 'Italy' => 'IT',
        'Belgium' => 'BE', 'Netherlands' => 'NL', 'Portugal' => 'PT', 'United Kingdom' => 'GB',
    ];

    public static function getEntityFqcn(): string
    {
        return Customer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Customer')
            ->setEntityLabelInPlural('Customers')
            ->setSearchFields(['email', 'firstName', 'lastName', 'city'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $a) => $a->setIcon('fa fa-pen'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $a) => $a->setIcon('fa fa-trash'))
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield EmailField::new('email');
            yield TextField::new('firstName');
            yield TextField::new('lastName');
            yield TextField::new('city');
            yield ChoiceField::new('country')->setChoices(self::COUNTRIES);
            yield DateTimeField::new('createdAt');

            return;
        }

        yield FormField::addFieldset('Identity')->setIcon('fa fa-user');
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield TextField::new('firstName');
        yield TextField::new('lastName');
        yield TelephoneField::new('phone');

        yield FormField::addFieldset('Address')->setIcon('fa fa-map-pin');
        yield TextField::new('addressLine');
        yield TextField::new('city');
        yield TextField::new('postalCode');
        yield ChoiceField::new('country')->setChoices(self::COUNTRIES);

        yield FormField::addFieldset('Lifecycle')->setIcon('fa fa-clock-rotate-left')->collapsible();
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(TextFilter::new('lastName'))
            ->add(ChoiceFilter::new('country')->setChoices(self::COUNTRIES)->canSelectMultiple())
            ->add(DateTimeFilter::new('createdAt', 'Signed up'))
            ->add(InFilter::new('city', 'City is one of'))
            ->add(NotNullFilter::new('phone', 'Has phone'));
    }
}
