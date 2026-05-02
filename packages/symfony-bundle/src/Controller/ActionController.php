<?php

declare(strict_types=1);

namespace Polysource\Bundle\Controller;

use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Routing\PolysourceUrlGenerator;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Exception\ResourceNotFoundException;
use Polysource\Core\Exception\UnsupportedOperationException;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Executes inline (single-record) and bulk (many-records) actions.
 *
 * Inline action: `POST /{prefix}/{resourceName}/{id}/{action}`.
 * Bulk action:   `POST /{prefix}/{resourceName}/batch/{action}` with form
 * data `ids[]=1&ids[]=2`.
 *
 * Returns a redirect to the resource index page on success/failure. Flash
 * messages and templated error pages arrive in Phase 3 (Twig theme).
 */
final readonly class ActionController
{
    public function __construct(
        private PolysourceUrlGenerator $urlGenerator,
    ) {
    }

    public function __invoke(AdminContext $context): Response
    {
        if (null === $context->recordId) {
            throw new ResourceNotFoundException(\sprintf('Action route for resource "%s" requires an "id" parameter.', $context->resource->getName()));
        }

        $action = self::findAction($context->resource, $context->action);
        if (!$action instanceof InlineActionInterface) {
            throw new UnsupportedOperationException(\sprintf('Action "%s" on resource "%s" is not an inline action.', $context->action, $context->resource->getName()));
        }

        $record = $context->resource->getDataSource()->find($context->recordId);
        if (null === $record) {
            throw new ResourceNotFoundException(\sprintf('Record "%s" not found in resource "%s".', $context->recordId, $context->resource->getName()));
        }

        $action->execute($record);

        return new RedirectResponse($this->urlGenerator->index($context->resource->getName()));
    }

    public function bulk(AdminContext $context): Response
    {
        $actionName = $context->request->attributes->get('action');
        if (!\is_string($actionName) || '' === $actionName) {
            throw new ResourceNotFoundException(\sprintf('Bulk action route for resource "%s" requires an "action" parameter.', $context->resource->getName()));
        }

        $action = self::findAction($context->resource, $actionName);
        if (!$action instanceof BulkActionInterface) {
            throw new UnsupportedOperationException(\sprintf('Action "%s" on resource "%s" is not a bulk action.', $actionName, $context->resource->getName()));
        }

        $rawIds = $context->request->request->all('ids');
        $records = [];
        foreach ($rawIds as $id) {
            if (!\is_scalar($id)) {
                continue;
            }
            $record = $context->resource->getDataSource()->find((string) $id);
            if (null !== $record) {
                $records[] = $record;
            }
        }

        $action->executeBatch($records);

        return new RedirectResponse($this->urlGenerator->index($context->resource->getName()));
    }

    private static function findAction(ResourceInterface $resource, string $name): ?ActionInterface
    {
        foreach ($resource->configureActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }
}
