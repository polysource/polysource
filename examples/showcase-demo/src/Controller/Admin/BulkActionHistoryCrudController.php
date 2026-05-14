<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Polysource\Filter\BulkActionHistory\Storage\Doctrine\BulkActionHistoryRecord;

/**
 * Showcase admin page for the v0.5.0 #8 bulk action history audit
 * log. Read-only (append-only log — the storage interface only
 * exposes `append()` and `recent()`; admin destructive ops are
 * NOT supported by the contract).
 *
 * Demonstrates `BulkActionHistoryService::recentForResource()` end
 * to end: each "Mark as cancelled" bulk action on the Orders index
 * (`OrderCrudController::bulkMarkCancelled`) writes an entry here.
 *
 * @since 0.5.2 (showcase wiring)
 */
final class BulkActionHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BulkActionHistoryRecord::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Bulk action')
            ->setEntityLabelInPlural('Bulk action history')
            ->setDefaultSort(['occurredAt' => 'DESC'])
            ->setSearchFields(['actionName', 'resourceName', 'ownerId'])
            ->setPaginatorPageSize(25);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Append-only log — the only write the contract allows is
        // BulkActionHistoryService::record(), called from the
        // domain bulk-action handlers. No new/edit/delete from the
        // admin: clearing entries would break the audit trail.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield DateTimeField::new('occurredAt', 'When')->setFormat('yyyy-MM-dd HH:mm:ss');
        yield TextField::new('ownerId', 'User')->setMaxLength(40);
        yield TextField::new('resourceName', 'Resource')->setMaxLength(40);
        yield TextField::new('actionName', 'Action');
        yield IntegerField::new('affectedCount', 'Rows touched');
        yield TextField::new('metadataJson', 'Metadata')
            ->onlyOnDetail()
            ->setHelp('JSON blob — action-specific payload (scope, target ids, etc.)');
    }
}
