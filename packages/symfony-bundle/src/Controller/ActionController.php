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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Executes inline (single-record) and bulk (many-records) actions.
 *
 * Inline action: `POST /{prefix}/{resourceName}/{id}/{action}`.
 * Bulk action:   `POST /{prefix}/{resourceName}/batch/{action}` with form
 * data `ids[]=1&ids[]=2`.
 *
 * Both endpoints require a CSRF token in the `_token` field. The token id
 * is `polysource_action`. Bulk requests with more than `max_bulk_ids`
 * identifiers are rejected with `400 Bad Request`.
 *
 * Returns a redirect to the resource index page on success/failure. Flash
 * messages and templated error pages arrive in Phase 3 (Twig theme).
 */
final readonly class ActionController
{
    public const CSRF_TOKEN_ID = 'polysource_action';

    public function __construct(
        private PolysourceUrlGenerator $urlGenerator,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private int $maxBulkIds = 500,
    ) {
    }

    public function __invoke(AdminContext $context): Response
    {
        // TODO(Phase-6): enforce $context->resource->getPermission() and
        // the action's getPermission() via PermissionInterface.

        $this->assertCsrf($context);

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
        // TODO(Phase-6): enforce $context->resource->getPermission() and
        // the action's getPermission() via PermissionInterface.

        $this->assertCsrf($context);

        $rawIds = $context->request->request->all('ids');
        if (\count($rawIds) > $this->maxBulkIds) {
            throw new BadRequestHttpException(\sprintf('Bulk action accepts at most %d ids per request, got %d.', $this->maxBulkIds, \count($rawIds)));
        }

        $actionName = $context->request->attributes->get('action');
        if (!\is_string($actionName) || '' === $actionName) {
            throw new ResourceNotFoundException(\sprintf('Bulk action route for resource "%s" requires an "action" parameter.', $context->resource->getName()));
        }

        $action = self::findAction($context->resource, $actionName);
        if (!$action instanceof BulkActionInterface) {
            throw new UnsupportedOperationException(\sprintf('Action "%s" on resource "%s" is not a bulk action.', $actionName, $context->resource->getName()));
        }

        $stringIds = [];
        foreach ($rawIds as $id) {
            if (\is_scalar($id)) {
                $stringIds[] = (string) $id;
            }
        }
        $stringIds = array_values(array_unique($stringIds));

        $records = [];
        foreach ($stringIds as $id) {
            $record = $context->resource->getDataSource()->find($id);
            if (null !== $record) {
                $records[] = $record;
            }
        }

        $action->executeBatch($records);

        return new RedirectResponse($this->urlGenerator->index($context->resource->getName()));
    }

    private function assertCsrf(AdminContext $context): void
    {
        $token = $context->request->request->get('_token');
        if (!\is_string($token) || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token))) {
            throw new AccessDeniedHttpException('Invalid or missing CSRF token.');
        }
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
