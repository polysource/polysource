<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Refund;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

final class RefundCrudController extends AbstractCrudController
{
    private const STATUS_CHOICES = [
        'Pending' => Refund::STATUS_PENDING,
        'Processed' => Refund::STATUS_PROCESSED,
        'Rejected' => Refund::STATUS_REJECTED,
    ];

    private const REASON_CHOICES = [
        'Defective' => Refund::REASON_DEFECTIVE,
        'Not as described' => Refund::REASON_NOT_AS_DESCRIBED,
        'Late delivery' => Refund::REASON_LATE_DELIVERY,
        'Changed mind' => Refund::REASON_CHANGED_MIND,
        'Other' => Refund::REASON_OTHER,
    ];

    public static function getEntityFqcn(): string
    {
        return Refund::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Refund')
            ->setEntityLabelInPlural('Refunds')
            ->setSearchFields(['order.reference', 'note'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(25);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield AssociationField::new('order');
        yield MoneyField::new('amountCents', 'Amount')->setCurrency('EUR')->setStoredAsCents();
        yield ChoiceField::new('reason')->setChoices(self::REASON_CHOICES);
        yield ChoiceField::new('status')->setChoices(self::STATUS_CHOICES);
        yield TextareaField::new('note')->hideOnIndex();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('processedAt')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(self::STATUS_CHOICES)->canSelectMultiple())
            ->add(ChoiceFilter::new('reason')->setChoices(self::REASON_CHOICES)->canSelectMultiple())
            ->add(DateTimeFilter::new('createdAt', 'Filed at'))
            ;
    }
}
