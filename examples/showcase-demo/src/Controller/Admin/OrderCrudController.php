<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;
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
            ->setPaginatorPageSize(25)
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Hard-delete disabled — Refund rows reference Order via a
        // non-nullable, non-cascading FK; deleting an order that has
        // even one refund crashes. The right business action is to
        // transition the order through OrderWorkflow to `cancelled` (and
        // then to `refunded` if money was already taken), which the
        // workflow-bridge transition buttons expose on the detail page.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->setIcon('fa fa-pen'))
            ->disable(Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT]);
    }

    public function configureFields(string $pageName): iterable
    {
        // Index — flat columns, no tabs (would break grid layout).
        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('reference');
            yield ChoiceField::new('status')->setChoices(self::STATUS_CHOICES)->renderAsBadges([
                Order::STATUS_CART => 'secondary',
                Order::STATUS_PAID => 'info',
                Order::STATUS_PREPARING => 'warning',
                Order::STATUS_SHIPPED => 'primary',
                Order::STATUS_DELIVERED => 'success',
                Order::STATUS_CANCELLED => 'danger',
                Order::STATUS_REFUNDED => 'dark',
            ]);
            yield AssociationField::new('customer');
            yield MoneyField::new('totalCents', 'Total')->setCurrency('EUR')->setStoredAsCents();
            yield DateTimeField::new('createdAt');
            yield DateTimeField::new('shippedAt');

            return;
        }

        // Detail / form pages — full EA grouping demo:
        //   FormField::addTab(...)      → tabs at the top of the page
        //     FormField::addColumn(...) → grid columns inside a tab
        //       FormField::addFieldset(...) → grouping inside a column
        yield FormField::addTab('Header', 'fa fa-file-invoice');
        yield FormField::addColumn(8);
        yield FormField::addFieldset('Order')->setIcon('fa fa-receipt');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('reference');
        yield ChoiceField::new('status')->setChoices(self::STATUS_CHOICES);
        yield AssociationField::new('customer');
        yield FormField::addColumn(4);
        yield FormField::addFieldset('Total')->setIcon('fa fa-coins');
        yield MoneyField::new('totalCents', 'Total')->setCurrency('EUR')->setStoredAsCents();

        yield FormField::addTab('Shipping & payment', 'fa fa-truck');
        yield FormField::addFieldset('Shipping')->setIcon('fa fa-box');
        yield TextField::new('shippingAddress');
        yield TextField::new('trackingNumber');
        yield FormField::addFieldset('Payment')->setIcon('fa fa-credit-card');
        yield TextField::new('paymentTransactionId', 'Transaction ID');

        // Lifecycle timestamps are workflow-driven (OrderWorkflow sets
        // paidAt / shippedAt / etc. on each transition). Editing them
        // by hand would corrupt the audit trail, so the whole tab is
        // detail-only — `hideOnForm()` would have hidden the fields
        // but left the tab empty on Edit, surfacing as "Lifecycle has
        // nothing inside" (reported during pre-flight QA).
        yield FormField::addTab('Lifecycle', 'fa fa-clock-rotate-left')->onlyOnDetail();
        yield DateTimeField::new('createdAt')->onlyOnDetail();
        yield DateTimeField::new('paidAt')->onlyOnDetail();
        yield DateTimeField::new('shippedAt')->onlyOnDetail();
        yield DateTimeField::new('deliveredAt')->onlyOnDetail();
        yield DateTimeField::new('cancelledAt')->onlyOnDetail();
        yield DateTimeField::new('refundedAt')->onlyOnDetail();
    }

    public function configureFilters(Filters $filters): Filters
    {
        // Polysource::tab(...) and Polysource::group(...) are markers
        // recognised by the bridge's _filters_modal.html.twig — the
        // filter modal renders them as Bootstrap nav-tabs + accordions
        // so dozens of filters stay legible.
        return $filters
            ->add(Polysource::tab('Identification'))
            ->add(TextFilter::new('reference'))
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUS_CHOICES)->canSelectMultiple())

            ->add(Polysource::tab('Dates'))
            ->add(Polysource::group('Created'))
            ->add(DateTimeFilter::new('createdAt', 'Order date'))
            ->add(Polysource::group('Paid'))
            ->add(BetweenDateFilter::new('paidAt', 'Paid between (range picker)'))

            ->add(Polysource::tab('Money'))
            ->add(NumericFilter::new('totalCents', 'Total (cents)'))

            ->add(Polysource::tab('Lifecycle'))
            ->add(NotNullFilter::new('shippedAt', 'Has shipped'))
            ->add(NotNullFilter::new('refundedAt', 'Has refunded'));
    }
}
