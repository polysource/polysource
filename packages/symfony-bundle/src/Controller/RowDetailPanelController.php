<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\RowDetail\EmbeddedListingRenderer;
use Polysource\Bundle\RowDetail\HasRowDetailsInterface;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Permission\PermissionInterface;
use Polysource\Core\Query\DataRecord;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Front controller for `GET /{prefix}/{resourceName}/{id}/detail-panel`
 * — the native listing's lazy row-detail endpoint (v1.1.0).
 *
 * Mirrors the bridge's RowDetailController semantics on the
 * PolysourceView pipeline:
 *  - the resource must opt in via {@see HasRowDetailsInterface}
 *    (404 otherwise — unconfigured resources don't expose the URL
 *    shape as an oracle);
 *  - the row-detail permission attribute is checked with the
 *    {@see DataRecord} as voter subject — the authoritative check,
 *    the index's chevron gate is cosmetic;
 *  - `?fragment=1` renders the host template bare (what the
 *    Stimulus controller injects); without it the same content is
 *    wrapped in a layout page — the ADR-027 no-JS baseline;
 *  - `no-store`: per-user, row-fresh content.
 */
final class RowDetailPanelController
{
    private const NO_STORE = ['Cache-Control' => 'no-cache, no-store, must-revalidate'];

    public function __construct(
        private readonly ControllerSupport $support,
        private readonly PermissionInterface $permission,
        private readonly EmbeddedListingRenderer $embeddedListingRenderer,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->support->assertResourceAccess($context->resource);

        $resource = $context->resource;
        if (!$resource instanceof HasRowDetailsInterface) {
            throw new NotFoundHttpException(\sprintf('Resource "%s" does not expose row details.', $resource->getName()));
        }

        if (null === $context->recordId) {
            throw new NotFoundHttpException(\sprintf('Detail-panel route for resource "%s" requires an "id" parameter.', $resource->getName()));
        }

        $record = $resource->getDataSource()->find($context->recordId);
        if (null === $record) {
            throw new NotFoundHttpException(\sprintf('Record "%s" not found in resource "%s".', $context->recordId, $resource->getName()));
        }

        $attribute = $resource->getRowDetailPermission();
        if (null !== $attribute && !$this->permission->isGranted($attribute, $record)) {
            throw new AccessDeniedHttpException(\sprintf('Access denied on row detail for "%s" (attribute %s).', $resource->getName(), $attribute));
        }

        $detail = $resource->getRowDetail($record);
        if (null === $detail) {
            throw new NotFoundHttpException(\sprintf('Record "%s" in resource "%s" has no row detail.', $context->recordId, $resource->getName()));
        }

        if ($detail->isListing()) {
            // Embedded-listing detail: the renderer reads its own
            // `rd_page` param off THIS panel request — each expanded
            // row is its own HTTP fetch, so nothing collides with
            // the outer listing's query string.
            $template = EmbeddedListingRenderer::TEMPLATE;
            $variables = [
                'context' => $context,
                'resource' => $resource,
                'record' => $record,
            ] + $this->embeddedListingRenderer->buildView($detail, $context->request);
        } else {
            \assert(null !== $detail->template);
            $template = $detail->template;
            $variables = [
                'context' => $context,
                'resource' => $resource,
                'record' => $record,
            ] + $detail->context;
        }

        if ($context->request->query->getBoolean('fragment')) {
            return new PolysourceView(
                template: $template,
                variables: $variables,
                headers: self::NO_STORE,
            );
        }

        return new PolysourceView(
            template: '@Polysource/row_detail_page.html.twig',
            variables: $variables + ['content_template' => $template],
            headers: self::NO_STORE,
        );
    }
}
