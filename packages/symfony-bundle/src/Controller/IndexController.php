<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\View\PolysourceView;

/**
 * Front controller for `GET /{prefix}/{resourceName}` (the resource index page).
 *
 * Returns a {@see PolysourceView} consumed by the response listener.
 */
final readonly class IndexController
{
    public function __construct(
        private ControllerSupport $support,
    ) {
    }

    public function __invoke(AdminContext $context): PolysourceView
    {
        $this->support->assertResourceAccess($context->resource);

        $page = $context->resource->getDataSource()->search($context->query);
        $actions = $this->support->collectActionViews($context->resource);

        return new PolysourceView(
            template: '@Polysource/index.html.twig',
            variables: [
                'context' => $context,
                'resource' => $context->resource,
                'page' => $page,
                'fields' => $this->support->collectFields($context->resource, 'index'),
                'inline_actions' => $actions['inline'],
                'bulk_actions' => $actions['bulk'],
            ],
        );
    }
}
