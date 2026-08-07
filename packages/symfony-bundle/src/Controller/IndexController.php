<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Query\DataPage;

/**
 * Front controller for `GET /{prefix}/{resourceName}` (the resource index page).
 *
 * Returns a {@see PolysourceView} consumed by the response listener.
 */
final class IndexController
{
    public function __construct(
        private readonly ControllerSupport $support,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->support->assertResourceAccess($context->resource);

        $page = $context->resource->getDataSource()->search($context->query);
        $actions = $this->support->collectActionViews($context->resource);

        // Materialise once so the per-record pass below and the
        // template's row loop both see the full page even when the
        // data source returned a one-shot Generator.
        $records = $page->asArray();
        $page = new DataPage($records, $page->total, $page->nextCursor, $page->prevCursor);

        // Per-record inline-action views: the voter gets the record
        // as subject and isDisplayed() gets a populated context, so
        // "Retry" can vanish on succeeded rows and per-row voters
        // actually fire. Keyed by identifier for the row loop.
        $recordActions = [];
        $rowDetails = [];
        foreach ($records as $record) {
            $recordActions[$record->identifier] = $this->support->collectRecordActionViews($context->resource, $record, 'index');
            $rowDetails[$record->identifier] = $this->support->isRowDetailAvailable($context->resource, $record);
        }

        // Empty-fields fallback: when the Resource declared no fields,
        // synthesise them from the first record's properties so the UI
        // doesn't render rows-without-columns. Cf. ControllerSupport::synthesiseFieldsFromRecord.
        $fields = $this->support->collectFields($context->resource, 'index');
        if ([] === $fields && [] !== $records) {
            $fields = ControllerSupport::synthesiseFieldsFromRecord($records[0]);
        }

        return new PolysourceView(
            template: '@Polysource/index.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'page' => $page,
                'fields' => $fields,
                'inline_actions' => $actions['inline'],
                'bulk_actions' => $actions['bulk'],
                'record_actions' => $recordActions,
                'row_details' => $rowDetails,
            ],
        );
    }
}
