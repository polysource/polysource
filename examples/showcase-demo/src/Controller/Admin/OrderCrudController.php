<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
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
use Polysource\Filter\BulkActionHistory\BulkActionHistoryService;
use Polysource\Filter\RecentRecords\RecentRecordsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class OrderCrudController extends AbstractCrudController
{
    /**
     * Both services are optional — the showcase always provides them,
     * but a host who installs only `polysource/easyadmin-filter-bridge`
     * without Doctrine + Security gets null. The detail/edit hooks
     * silently skip when null so the controller works in either setup.
     *
     * @since 0.5.1 (showcase wiring)
     */
    public function __construct(
        private readonly ?RecentRecordsService $recentRecords = null,
        private readonly ?BulkActionHistoryService $bulkHistory = null,
        private readonly ?ManagerRegistry $registry = null,
    ) {
    }

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
        // v0.3.0 #12 — Export action. Links to the bridge's
        // `polysource_export` route, scoped to the Order resource.
        // The link carries the current ?filters[...] slice so the
        // export honours what the user is looking at
        // (since v0.5.0 — UrlFilterApplier).
        $exportCsv = Action::new('exportCsv', 'Export CSV', 'fa fa-file-csv')
            ->linkToRoute('polysource_export', [
                'resource' => str_replace('\\', '\\\\', Order::class),
                'format' => 'csv',
            ])
            ->setCssClass('btn-sm btn-outline-secondary')
            ->createAsGlobalAction()
        ;
        $exportXlsx = Action::new('exportXlsx', 'Export XLSX', 'fa fa-file-excel')
            ->linkToRoute('polysource_export', [
                'resource' => str_replace('\\', '\\\\', Order::class),
                'format' => 'xlsx',
            ])
            ->setCssClass('btn-sm btn-outline-secondary')
            ->createAsGlobalAction()
        ;

        // v0.5.0 #9 — Preview bulk count. Lands on the showcase
        // dry-run preview page that calls `UrlFilterApplier` the
        // same way the JSON `MatchingCountController` does — but
        // renders the result server-side so the feature is visible
        // in a screenshot without JS modal wiring.
        $previewCount = Action::new('previewBulkCount', 'Preview bulk count', 'fa fa-eye')
            ->linkToRoute('showcase_matching_count_preview_orders')
            ->setCssClass('btn-sm btn-outline-info')
            ->createAsGlobalAction()
        ;

        // v0.5.0 #8 — Bulk action with history tracking. "Mark
        // selected as cancelled" demonstrates the history audit
        // trail: each invocation records a BulkActionEntry that
        // admins can later inspect via `BulkActionHistoryService`.
        $bulkCancel = Action::new('bulkMarkCancelled', 'Mark as cancelled', 'fa fa-ban')
            ->linkToCrudAction('bulkMarkCancelled')
            ->setCssClass('btn-sm btn-outline-danger')
            ->addCssClass('action-bulk')
            ->createAsBatchAction()
        ;

        // Hard-delete disabled — Refund rows reference Order via a
        // non-nullable, non-cascading FK; deleting an order that has
        // even one refund crashes. The right business action is to
        // transition the order through OrderWorkflow to `cancelled` (and
        // then to `refunded` if money was already taken), which the
        // workflow-bridge transition buttons expose on the detail page.
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportCsv)
            ->add(Crud::PAGE_INDEX, $exportXlsx)
            ->add(Crud::PAGE_INDEX, $previewCount)
            ->addBatchAction($bulkCancel)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->setIcon('fa fa-pen'))
            ->disable(Action::DELETE)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, Action::EDIT]);
    }

    /**
     * v0.5.0 #8 — Bulk action handler. Marks the selected orders as
     * cancelled and records the action in the audit log via
     * `BulkActionHistoryService::record()`.
     *
     * Cross-page scope (v0.4.0 #19): the controller reads the
     * `bulk_scope` form field to decide between "selected only" or
     * "every row matching the current filter slice". The latter
     * uses the same `?filters[...]` URL parser that powers the
     * filter-aware export (v0.5.0 #9).
     */
    #[AdminRoute('/bulk-mark-cancelled', 'bulk_mark_cancelled')]
    public function bulkMarkCancelled(AdminContext $context, Request $request): Response
    {
        $selected = $request->request->all('batchActionEntityIds');
        $scope = (string) $request->request->get('bulk_scope', 'selected');

        // For this showcase demo we cancel ONLY the explicit selection
        // — production hosts wire the `all_matching` branch via
        // `UrlFilterApplier` + EA's QueryBuilder. Keeping the showcase
        // path simple so the audit-trail integration stays the focus.
        $em = $this->registry?->getManagerForClass(Order::class);
        if (null === $em) {
            throw $this->createNotFoundException('Doctrine not wired.');
        }
        $count = 0;
        foreach ($selected as $id) {
            $order = $em->find(Order::class, $id);
            if (null === $order) {
                continue;
            }
            $order->setStatus(Order::STATUS_CANCELLED);
            ++$count;
        }
        $em->flush();

        $this->bulkHistory?->record(
            id: Uuid::v7()->toRfc4122(),
            resourceName: 'orders',
            actionName: 'mark_cancelled',
            affectedCount: $count,
            metadata: [
                'scope' => $scope,
                'targetIds' => array_values($selected),
            ],
        );

        $this->addFlash('success', \sprintf('Marked %d orders as cancelled.', $count));

        return $this->redirect($context->getReferrer() ?? '/admin');
    }

    /**
     * v0.5.0 #6 — Recently viewed records. Each time the user opens
     * the detail page of an order, the service upserts the
     * (user, "orders", reference) triplet with the current timestamp.
     * A "Recently viewed" widget on the dashboard / index can then
     * call `RecentRecordsService::recentForCurrentUser('orders')`
     * to render the list most-recent-first.
     */
    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $this->trackRecentView($context);

        return parent::detail($context);
    }

    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->trackRecentView($context);

        return parent::edit($context);
    }

    private function trackRecentView(AdminContext $context): void
    {
        if (null === $this->recentRecords) {
            return;
        }
        $order = $context->getEntity()->getInstance();
        if (!$order instanceof Order) {
            return;
        }
        $this->recentRecords->recordView(
            resourceName: 'orders',
            recordId: (string) $order->getId(),
            label: \sprintf('%s — %s', $order->getReference(), $order->getCustomer()?->getEmail() ?? 'no customer'),
        );
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
