<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
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
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email');
        yield TextField::new('firstName');
        yield TextField::new('lastName');
        yield TelephoneField::new('phone')->hideOnIndex();
        yield TextField::new('addressLine')->hideOnIndex();
        yield TextField::new('city');
        yield TextField::new('postalCode')->hideOnIndex();
        yield ChoiceField::new('country')->setChoices(self::COUNTRIES);
        yield DateTimeField::new('createdAt')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('email'))
            ->add(TextFilter::new('lastName'))
            ->add(ChoiceFilter::new('country')->setChoices(self::COUNTRIES)->canSelectMultiple())
            // The bridge auto-enhances DateTimeFilter with presets:
            // today / 7 days / 30 days / this month / custom range.
            ->add(DateTimeFilter::new('createdAt', 'Signed up'))
            // Custom bridge filters.
            ->add(InFilter::new('city', 'City is one of'))
            ->add(NotNullFilter::new('phone', 'Has phone'))
            ;
    }
}
