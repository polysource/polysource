<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;

final class OrderCrudController extends AbstractCrudController
{
    private const STATUS_CHOICES = [
        'Cart' => Order::STATUS_CART,
        'Paid' => Order::STATUS_PAID,
        'Preparing' => Order::STATUS_PREPARING,
        'Shipped' => Order::STATUS_SHIPPED,
        'Delivered' => Order::STATUS_DELIVERED,
        'Cancelled' => Order::STATUS_CANCELLED,
        'Refunded' => Order::STATUS_REFUNDED,
    ];

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Order')
            ->setEntityLabelInPlural('Orders')
            ->setSearchFields(['reference', 'customer.email', 'paymentTransactionId', 'trackingNumber'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('reference');
        yield ChoiceField::new('status')->setChoices(self::STATUS_CHOICES);
        yield AssociationField::new('customer');
        yield MoneyField::new('totalCents', 'Total')->setCurrency('EUR')->setStoredAsCents();
        yield TextField::new('shippingAddress')->hideOnIndex();
        yield TextField::new('paymentTransactionId', 'Tx ID')->hideOnIndex();
        yield TextField::new('trackingNumber')->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('paidAt')->hideOnForm();
        yield DateTimeField::new('shippedAt')->hideOnForm();
        yield DateTimeField::new('deliveredAt')->hideOnForm()->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('reference'))
            // ChoiceFilter with multi → bridge enhances to Select2 chips.
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUS_CHOICES)->canSelectMultiple())
            ->add(DateTimeFilter::new('createdAt', 'Order date'))
            ->add(NumericFilter::new('totalCents', 'Total (cents)'))
            // Custom bridge filters — each on a different property so EA's
            // one-filter-per-property rule is honored.
            ->add(BetweenDateFilter::new('paidAt', 'Paid between (range picker)'))
            // Find unshipped orders: shippedAt IS NULL when filter is "no".
            ->add(NotNullFilter::new('shippedAt', 'Has shipped'))
            ->add(NotNullFilter::new('refundedAt', 'Has refunded'))
            ;
    }
}
