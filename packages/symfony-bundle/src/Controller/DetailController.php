<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;
use Polysource\Core\Exception\ResourceNotFoundException;

/**
 * Front controller for `GET /{prefix}/{resourceName}/{id}` (single record detail).
 */
final class DetailController
{
    public function __construct(
        private readonly ControllerSupport $support,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->support->assertResourceAccess($context->resource);

        if (null === $context->recordId) {
            throw new ResourceNotFoundException(\sprintf('Detail route for resource "%s" requires an "id" parameter.', $context->resource->getName()));
        }

        $record = $context->resource->getDataSource()->find($context->recordId);
        if (null === $record) {
            throw new ResourceNotFoundException(\sprintf('Record "%s" not found in resource "%s".', $context->recordId, $context->resource->getName()));
        }

        return new PolysourceView(
            template: '@Polysource/detail.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'record' => $record,
                'fields' => $this->support->collectFields($context->resource, 'detail'),
                'inline_actions' => $this->support->collectActionViews($context->resource)['inline'],
            ],
        );
    }
}
